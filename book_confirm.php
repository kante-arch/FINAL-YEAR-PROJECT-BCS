<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/seat_logic.php";
require_once __DIR__ . "/includes/notifications.php";
$user = require_login($pdo);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: book.php");
    exit;
}

$seat_ids_raw = $_POST["seat_ids"] ?? "";
if (is_array($seat_ids_raw)) {
    $seat_ids = array_values(array_unique(array_filter(array_map('intval', $seat_ids_raw))));
} else {
    $seat_ids = array_values(array_unique(array_filter(array_map('intval', explode(",", (string) $seat_ids_raw)))));
}

if (empty($seat_ids)) {
    flash_set("Please select at least one seat before confirming your booking.", "error");
    header("Location: book.php");
    exit;
}

$train_id = (int) $_POST["train_id"];
$origin_id = (int) $_POST["origin_id"];
$destination_id = (int) $_POST["destination_id"];
$travel_date = $_POST["travel_date"] ?? date("Y-m-d");

$train_stmt = $pdo->prepare("SELECT * FROM train WHERE id = ?");
$train_stmt->execute([$train_id]);
$train = $train_stmt->fetch(PDO::FETCH_ASSOC);

$origin_stmt = $pdo->prepare("SELECT * FROM station WHERE id = ?");
$origin_stmt->execute([$origin_id]);
$origin = $origin_stmt->fetch(PDO::FETCH_ASSOC);

$dest_stmt = $pdo->prepare("SELECT * FROM station WHERE id = ?");
$dest_stmt->execute([$destination_id]);
$destination = $dest_stmt->fetch(PDO::FETCH_ASSOC);

$train_stmt = $pdo->prepare("SELECT * FROM train WHERE id = ?");
$train_stmt->execute([$train_id]);
$train = $train_stmt->fetch(PDO::FETCH_ASSOC);

$origin_stmt = $pdo->prepare("SELECT * FROM station WHERE id = ?");
$origin_stmt->execute([$origin_id]);
$origin = $origin_stmt->fetch(PDO::FETCH_ASSOC);

$dest_stmt = $pdo->prepare("SELECT * FROM station WHERE id = ?");
$dest_stmt->execute([$destination_id]);
$destination = $dest_stmt->fetch(PDO::FETCH_ASSOC);

if (!$train || !$origin || !$destination) {
    flash_set("Something went wrong with your selection. Please try again.", "error");
    header("Location: book.php");
    exit;
}

$seat_stmt = $pdo->prepare("SELECT * FROM seat WHERE id = ?");
$available = get_available_seats($pdo, $train, $origin, $destination);
$available_ids = array_column($available, "id");
$selected_seats = [];
foreach ($seat_ids as $seat_id) {
    $seat_stmt->execute([$seat_id]);
    $seat = $seat_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$seat || !in_array((int) $seat["id"], $available_ids, true)) {
        flash_set("Sorry, one or more selected seats are no longer available. Please choose again.", "error");
        header("Location: book.php");
        exit;
    }
    $selected_seats[] = $seat;
}

$total_fare = 0.0;
foreach ($selected_seats as $seat) {
    $total_fare += (float) calculate_fare($train, $origin, $destination);
}

$user_stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
$user_stmt->execute([$user["id"]]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if ((float) $user["wallet_balance"] < $total_fare) {
    flash_set("Insufficient wallet balance for these seats. Total required: " . number_format($total_fare, 0) . " TZS.", "error");
    header("Location: wallet.php");
    exit;
}

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE user SET wallet_balance = wallet_balance - ? WHERE id = ?")
        ->execute([$total_fare, $user["id"]]);

    $created_booking_ids = [];
    foreach ($selected_seats as $seat) {
        $qr_code = generate_qr_code_string();
        $insert = $pdo->prepare("
            INSERT INTO booking (seat_id, user_id, origin_station_id, destination_station_id, fare_paid, status, qr_code, travel_date)
            VALUES (?, ?, ?, ?, ?, 'active', ?, ?)
        ");
        $insert->execute([$seat["id"], $user["id"], $origin["id"], $destination["id"], calculate_fare($train, $origin, $destination), $qr_code, $travel_date]);
        $booking_id = $pdo->lastInsertId();
        $created_booking_ids[] = $booking_id;

        $pdo->prepare("INSERT INTO transaction_log (user_id, amount, kind, note) VALUES (?, ?, 'payment', ?)")
            ->execute([$user["id"], -(float) calculate_fare($train, $origin, $destination), "Ticket {$origin['name']} -> {$destination['name']} ({$seat['seat_number']})"]);

        notify_booking_confirmed($pdo, $user, [
            "coach_number" => $seat["coach_number"],
            "seat_number" => $seat["seat_number"],
            "origin_name" => $origin["name"],
            "destination_name" => $destination["name"],
            "fare" => calculate_fare($train, $origin, $destination),
            "qr_code" => $qr_code,
        ]);
    }

    $pdo->commit();

    flash_set("Booking successful! " . count($selected_seats) . " seat" . (count($selected_seats) > 1 ? "s" : "") . " booked for your group trip.", "success");
    header("Location: dashboard.php");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    flash_set($e->getMessage(), "error");
    header("Location: wallet.php");
    exit;
}
