<?php
/**
 * api/available_seats.php
 * ------------------------
 * Called via fetch() from book.php whenever the passenger changes
 * train / origin / destination. Returns JSON describing which seats
 * are free for that exact segment right now -- this is what makes
 * the seat map "live" and interactive instead of a static list.
 */
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/seat_logic.php";
header("Content-Type: application/json");

require_login($pdo); // must be logged in to query this

$train_id = (int) ($_GET["train_id"] ?? 0);
$origin_id = (int) ($_GET["origin_id"] ?? 0);
$destination_id = (int) ($_GET["destination_id"] ?? 0);
$travel_date = $_GET["travel_date"] ?? date("Y-m-d");

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
    http_response_code(400);
    echo json_encode(["error" => "Invalid train or station selection."]);
    exit;
}

if ((int) $origin["id"] === (int) $destination["id"]) {
    http_response_code(400);
    echo json_encode(["error" => "Origin and destination cannot be the same station."]);
    exit;
}

$available = get_available_seats($pdo, $train, $origin, $destination);
$available_ids = array_column($available, "id");
$fare = calculate_fare($train, $origin, $destination);

$all_seats_stmt = $pdo->prepare("SELECT * FROM seat WHERE train_id = ? ORDER BY coach_number, seat_number");
$all_seats_stmt->execute([$train["id"]]);
$all_seats = $all_seats_stmt->fetchAll(PDO::FETCH_ASSOC);

$seat_map = array_map(function ($s) use ($available_ids) {
    return [
        "id" => (int) $s["id"],
        "coach_number" => (int) $s["coach_number"],
        "seat_number" => $s["seat_number"],
        "available" => in_array($s["id"], $available_ids),
    ];
}, $all_seats);

echo json_encode([
    "seats" => $seat_map,
    "fare" => $fare,
    "travel_date" => $travel_date,
    "departure_label" => departure_period_label($train["departure_time"] ?? ""),
    "route_label" => "{$origin["name"]} to {$destination["name"]}"
]);
