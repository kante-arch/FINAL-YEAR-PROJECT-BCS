<?php
/**
 * seat_logic.php
 * --------------
 * The "smart" part of the system: the dynamic seat segment algorithm
 * from your project proposal. Plain PHP functions, no framework magic,
 * so you can read this file top to bottom and explain every line.
 *
 * Core idea
 * ---------
 * A normal ticketing system books a seat for the WHOLE journey
 * (Dar -> Dodoma), even if the passenger only travels Dar -> Morogoro.
 * That wastes the Morogoro -> Dodoma portion of the seat ("ghost seat").
 *
 * Here, a booking is stored as a SEGMENT: [origin_station, destination_station].
 * Two bookings can share the same seat as long as their segments don't
 * overlap. When a passenger exits early, we shrink their segment down
 * to where they actually got off, which automatically re-opens the
 * unused portion for a new booking, and we refund the distance they
 * did not travel.
 */

function stations_in_order(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM station ORDER BY order_index ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function distance_km(array $station_a, array $station_b): float {
    return abs($station_b["km_from_origin"] - $station_a["km_from_origin"]);
}

function calculate_fare(array $train, array $origin, array $destination): float {
    return round(distance_km($origin, $destination) * $train["fare_per_km"], 2);
}

function departure_period_label(string $time): string {
    $time = trim($time);
    if ($time === "") {
        return "Departure time not set";
    }

    $parts = explode(":", $time);
    $hour = (int) ($parts[0] ?? 0);

    if ($hour >= 0 && $hour < 6) {
        return "Mid-night ({$time})";
    }
    if ($hour === 12) {
        return "Noon ({$time})";
    }
    if ($hour >= 6 && $hour < 12) {
        return "Morning ({$time})";
    }
    if ($hour >= 12 && $hour < 16) {
        return "Afternoon ({$time})";
    }
    if ($hour >= 16 && $hour < 19) {
        return "Evening ({$time})";
    }
    return "Night ({$time})";
}

function segment_bounds(int $start, int $end): array {
    return [min($start, $end), max($start, $end)];
}

/**
 * Two segments overlap if one starts before the other ends.
 * Same logic used for meeting-room / calendar overlap checks.
 */
function segments_overlap(int $a_start, int $a_end, int $b_start, int $b_end): bool {
    return $a_start < $b_end && $b_start < $a_end;
}

/**
 * Return every seat on $train that is free for the requested
 * origin -> destination range.
 *
 * A seat is free if NONE of its existing bookings overlap the
 * requested segment. Bookings already marked 'exited' only block
 * up to the point the passenger actually left -- that's the
 * "harvesting" effect that frees up the rest of the seat.
 */
function get_available_seats(PDO $pdo, array $train, array $origin, array $destination): array {
    [$requested_start, $requested_end] = segment_bounds((int) $origin["order_index"], (int) $destination["order_index"]);

    $seats_stmt = $pdo->prepare("SELECT * FROM seat WHERE train_id = ? ORDER BY seat_number");
    $seats_stmt->execute([$train["id"]]);
    $seats = $seats_stmt->fetchAll(PDO::FETCH_ASSOC);

    $bookings_stmt = $pdo->prepare("
        SELECT b.seat_id, b.status,
               os.order_index AS origin_order,
               ds.order_index AS destination_order,
               ex.order_index AS exit_order
        FROM booking b
        JOIN station os ON os.id = b.origin_station_id
        JOIN station ds ON ds.id = b.destination_station_id
        LEFT JOIN station ex ON ex.id = b.actual_exit_station_id
        JOIN seat s ON s.id = b.seat_id
        WHERE s.train_id = ? AND b.status != 'cancelled'
    ");
    $bookings_stmt->execute([$train["id"]]);
    $bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

    $bookings_by_seat = [];
    foreach ($bookings as $b) {
        $bookings_by_seat[$b["seat_id"]][] = $b;
    }

    $available = [];
    foreach ($seats as $seat) {
        $blocked = false;
        foreach ($bookings_by_seat[$seat["id"]] ?? [] as $b) {
            [$b_start, $b_end] = segment_bounds((int) $b["origin_order"], (int) $b["destination_order"]);

            if ($b["status"] === "exited" && $b["exit_order"] !== null) {
                [$b_start, $b_end] = segment_bounds((int) $b["origin_order"], (int) $b["exit_order"]);
            }

            if (segments_overlap($requested_start, $requested_end, $b_start, $b_end)) {
                $blocked = true;
                break;
            }
        }
        if (!$blocked) {
            $available[] = $seat;
        }
    }

    return $available;
}

function generate_qr_code_string(): string {
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $code = "";
    for ($i = 0; $i < 10; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Create a new booking, charging the passenger's wallet.
 * Throws an Exception if the wallet balance is too low.
 * Returns the new booking's id and the fare charged.
 */
function book_seat(PDO $pdo, array $seat, array $user, array $train, array $origin, array $destination): array {
    $fare = calculate_fare($train, $origin, $destination);
    if ($user["wallet_balance"] < $fare) {
        throw new Exception("Insufficient wallet balance. Fare is $fare TZS, wallet has {$user['wallet_balance']} TZS.");
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE user SET wallet_balance = wallet_balance - ? WHERE id = ?")
            ->execute([$fare, $user["id"]]);

        $qr_code = generate_qr_code_string();
        $pdo->prepare("
            INSERT INTO booking (seat_id, user_id, origin_station_id, destination_station_id, fare_paid, status, qr_code)
            VALUES (?, ?, ?, ?, ?, 'active', ?)
        ")->execute([$seat["id"], $user["id"], $origin["id"], $destination["id"], $fare, $qr_code]);
        $booking_id = $pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO transaction_log (user_id, amount, kind, note)
            VALUES (?, ?, 'payment', ?)
        ")->execute([$user["id"], -$fare, "Ticket {$origin['name']} -> {$destination['name']}"]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ["booking_id" => $booking_id, "fare" => $fare];
}

/**
 * Called when the admin dashboard simulates a QR gate scan. If the
 * passenger got off earlier than their paid destination, we:
 *   1. Mark the booking 'exited' and record where they actually left.
 *   2. Refund the fare for the distance they did NOT travel.
 *   3. The freed portion becomes automatically bookable again, because
 *      get_available_seats() now only checks up to the actual exit point.
 *
 * Returns the refund amount (0 if the passenger rode to their full
 * paid destination -- nothing to harvest).
 */
function process_exit_scan(PDO $pdo, array $booking, array $exit_station): float {
    // Fetch related rows we need for the fare math.
    $train_stmt = $pdo->prepare("
        SELECT t.* FROM train t JOIN seat s ON s.train_id = t.id WHERE s.id = ?
    ");
    $train_stmt->execute([$booking["seat_id"]]);
    $train = $train_stmt->fetch(PDO::FETCH_ASSOC);

    $origin_stmt = $pdo->prepare("SELECT * FROM station WHERE id = ?");
    $origin_stmt->execute([$booking["origin_station_id"]]);
    $origin = $origin_stmt->fetch(PDO::FETCH_ASSOC);

    $paid_dest_stmt = $pdo->prepare("SELECT * FROM station WHERE id = ?");
    $paid_dest_stmt->execute([$booking["destination_station_id"]]);
    $paid_destination = $paid_dest_stmt->fetch(PDO::FETCH_ASSOC);

    // If they exited at or after their paid stop, nothing to harvest.
    if ((int) $exit_station["order_index"] >= (int) $paid_destination["order_index"]) {
        $pdo->prepare("UPDATE booking SET status = 'exited', actual_exit_station_id = ? WHERE id = ?")
            ->execute([$exit_station["id"], $booking["id"]]);
        return 0.0;
    }

    $travelled_km = distance_km($origin, $exit_station);
    $paid_km = distance_km($origin, $paid_destination);
    $unused_km = $paid_km - $travelled_km;
    $refund = round($unused_km * $train["fare_per_km"], 2);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE booking SET status = 'exited', actual_exit_station_id = ? WHERE id = ?")
            ->execute([$exit_station["id"], $booking["id"]]);

        $pdo->prepare("UPDATE user SET wallet_balance = wallet_balance + ? WHERE id = ?")
            ->execute([$refund, $booking["user_id"]]);

        $pdo->prepare("
            INSERT INTO transaction_log (user_id, amount, kind, note)
            VALUES (?, ?, 'refund', ?)
        ")->execute([$booking["user_id"], $refund, "Early exit at {$exit_station['name']} - unused distance refunded"]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $refund;
}
