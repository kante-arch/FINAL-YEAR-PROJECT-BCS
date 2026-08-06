<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";
$user = require_login($pdo);

$booking_id = (int) ($_GET["id"] ?? 0);
$stmt = $pdo->prepare("
    SELECT b.*, s.coach_number, s.seat_number, t.name AS train_name, t.departure_time,
           os.name AS origin_name, ds.name AS destination_name,
           u.full_name AS passenger_name
    FROM booking b
    JOIN seat s ON s.id = b.seat_id
    JOIN train t ON t.id = s.train_id
    JOIN station os ON os.id = b.origin_station_id
    JOIN station ds ON ds.id = b.destination_station_id
    JOIN user u ON u.id = b.user_id
    WHERE b.id = ?
");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    flash_set("Ticket not found.", "error");
    header("Location: dashboard.php");
    exit;
}
if ((int) $booking["user_id"] !== (int) $user["id"] && !$user["is_admin"]) {
    flash_set("You cannot view someone else's ticket.", "error");
    header("Location: dashboard.php");
    exit;
}

$page_title = "Ticket";
require __DIR__ . "/includes/header.php";
?>
<section class="ticket-card">
    <h2>Your ticket</h2>
    <div class="ticket-body">
        <div class="ticket-info">
            <p><strong>Passenger:</strong> <?= htmlspecialchars($booking["passenger_name"]) ?></p>
            <p><strong>Train:</strong> <?= htmlspecialchars($booking["train_name"]) ?> (<?= htmlspecialchars($booking["departure_time"]) ?>)</p>
            <p><strong>Seat:</strong> <?= htmlspecialchars("Coach " . $booking["coach_number"] . " - " . $booking["seat_number"]) ?></p>
            <p><strong>Route:</strong> <?= htmlspecialchars($booking["origin_name"]) ?> &rarr; <?= htmlspecialchars($booking["destination_name"]) ?></p>
            <p><strong>Fare paid:</strong> <?= number_format($booking["fare_paid"], 0) ?> TZS</p>
            <p><strong>Status:</strong> <span class="badge badge-<?= htmlspecialchars($booking["status"]) ?>"><?= htmlspecialchars($booking["status"]) ?></span></p>
            <p><strong>Ticket code:</strong> <?= htmlspecialchars($booking["qr_code"]) ?></p>
        </div>
        <div class="ticket-qr" id="qr-holder"></div>
    </div>
    <p><a href="dashboard.php">&larr; Back to my tickets</a></p>
</section>

<script src="assets/js/qrcode.min.js"></script>
<script>
    const ticketQrData = {
        passenger_name: <?= json_encode($booking["passenger_name"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        train: <?= json_encode($booking["train_name"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        seat: <?= json_encode("Coach " . $booking["coach_number"] . " - " . $booking["seat_number"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        route: <?= json_encode($booking["origin_name"] . " -> " . $booking["destination_name"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        fare_paid: <?= json_encode((float) $booking["fare_paid"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        status: <?= json_encode($booking["status"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        ticket_code: <?= json_encode($booking["qr_code"], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
    };

    new QRCode(document.getElementById("qr-holder"), {
        text: JSON.stringify(ticketQrData),
        width: 160,
        height: 160
    });
</script>
<?php require __DIR__ . "/includes/footer.php"; ?>
