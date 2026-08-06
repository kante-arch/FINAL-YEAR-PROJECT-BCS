<?php
/**
 * exit.php
 * --------
 * Lets a passenger end their own trip early -- e.g. a sudden emergency
 * forces them off at an earlier station. This mirrors what a real
 * automatic gate would do when it scans their ticket QR code on the
 * way out: no station staff need to manually process every passenger,
 * which avoids the queues/congestion a manual-only process would cause.
 *
 * The admin dashboard still has its own "Scan QR" action too, for
 * cases where staff need to step in on a passenger's behalf (e.g. a
 * lost phone, a customer-service call-in) -- but this self-service
 * route is the normal path.
 */
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/seat_logic.php";
require_once __DIR__ . "/includes/notifications.php";
$user = require_login($pdo);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit;
}

$booking_id = (int) ($_POST["booking_id"] ?? 0);
$station_id = (int) ($_POST["station_id"] ?? 0);

$booking_stmt = $pdo->prepare("SELECT * FROM booking WHERE id = ?");
$booking_stmt->execute([$booking_id]);
$booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);

$station_stmt = $pdo->prepare("SELECT * FROM station WHERE id = ?");
$station_stmt->execute([$station_id]);
$exit_station = $station_stmt->fetch(PDO::FETCH_ASSOC);

// Security check: passengers can only end their OWN active bookings.
if (!$booking || !$exit_station || (int) $booking["user_id"] !== (int) $user["id"]) {
    flash_set("That ticket could not be found.", "error");
    header("Location: dashboard.php");
    exit;
}
if ($booking["status"] !== "active") {
    flash_set("This ticket has already ended.", "error");
    header("Location: dashboard.php");
    exit;
}

$refund = process_exit_scan($pdo, $booking, $exit_station);

// Re-fetch the user's fresh wallet balance for the notification/flash message.
$fresh_stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
$fresh_stmt->execute([$user["id"]]);
$fresh_user = $fresh_stmt->fetch(PDO::FETCH_ASSOC);

if ($refund > 0) {
    notify_refund_processed($pdo, $fresh_user, $refund, $exit_station["name"]);
    flash_set("You've exited at {$exit_station['name']}. " . number_format($refund, 0)
        . " TZS refunded for the distance you didn't travel.", "success");
} else {
    flash_set("You've completed your journey to {$exit_station['name']}. No refund due.", "success");
}

header("Location: dashboard.php");
exit;
