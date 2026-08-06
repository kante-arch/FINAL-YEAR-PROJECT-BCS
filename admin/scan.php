<?php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/seat_logic.php";
require_once __DIR__ . "/../includes/notifications.php";
require_login($pdo, true);

$booking_id = (int) ($_POST["booking_id"] ?? 0);
$station_id = (int) ($_POST["station_id"] ?? 0);

$booking_stmt = $pdo->prepare("SELECT * FROM booking WHERE id = ?");
$booking_stmt->execute([$booking_id]);
$booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);

$station_stmt = $pdo->prepare("SELECT * FROM station WHERE id = ?");
$station_stmt->execute([$station_id]);
$exit_station = $station_stmt->fetch(PDO::FETCH_ASSOC);

$name_stmt = $pdo->prepare("SELECT full_name FROM user WHERE id = ?");
$name_stmt->execute([$booking["user_id"]]);
$passenger_name = $name_stmt->fetchColumn();

if (!$booking || !$exit_station) {
    flash_set("Invalid booking or station.", "error");
    header("Location: dashboard.php");
    exit;
}

$refund = process_exit_scan($pdo, $booking, $exit_station);

if ($refund > 0) {
    $passenger_stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
    $passenger_stmt->execute([$booking["user_id"]]);
    $passenger = $passenger_stmt->fetch(PDO::FETCH_ASSOC);
    notify_refund_processed($pdo, $passenger, $refund, $exit_station["name"]);

    flash_set("Seat harvested. $passenger_name refunded " . number_format($refund, 0)
        . " TZS. Remaining segment is now bookable by others.", "success");
} else {
    flash_set("$passenger_name exited at their paid destination. No refund due.", "success");
}

header("Location: dashboard.php");
exit;
