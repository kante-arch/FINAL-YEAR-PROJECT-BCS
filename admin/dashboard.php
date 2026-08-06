<?php
$__in_admin = true;
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/seat_logic.php";
$user = require_login($pdo, true);

$stmt = $pdo->query("
    SELECT b.*, s.coach_number, s.seat_number, os.name AS origin_name, ds.name AS destination_name, u.full_name
    FROM booking b
    JOIN seat s ON s.id = b.seat_id
    JOIN station os ON os.id = b.origin_station_id
    JOIN station ds ON ds.id = b.destination_station_id
    JOIN user u ON u.id = b.user_id
    WHERE b.status = 'active'
    ORDER BY b.created_at DESC
");
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stations = stations_in_order($pdo);

$page_title = "Admin Dashboard";
require __DIR__ . "/../includes/header.php";
?>
<h2>Station Admin Dashboard</h2>
<p class="hint">Passengers can now end their own trip early from their own dashboard --
   this screen is for staff to assist on a passenger's behalf (e.g. a lost phone,
   a call-in), not the normal path. Pick an active booking and the station where
   that passenger is exiting; if it's earlier than their paid destination, the
   system automatically refunds the unused distance and frees that portion of the seat.</p>

<div class="table-wrap">
<table>
    <thead><tr><th>Passenger</th><th>Seat</th><th>Paid Route</th><th>Fare</th><th>Simulate exit at</th></tr></thead>
    <tbody>
        <?php if (empty($bookings)): ?>
            <tr><td colspan="5">No active bookings right now.</td></tr>
        <?php else: foreach ($bookings as $b): ?>
        <tr>
            <td><?= htmlspecialchars($b["full_name"]) ?></td>
            <td><?= htmlspecialchars("Coach " . $b["coach_number"] . " - " . $b["seat_number"]) ?></td>
            <td><?= htmlspecialchars($b["origin_name"]) ?> &rarr; <?= htmlspecialchars($b["destination_name"]) ?></td>
            <td><?= number_format($b["fare_paid"], 0) ?> TZS</td>
            <td>
                <form method="POST" action="scan.php" class="inline-form">
                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                    <select name="station_id">
                        <?php foreach ($stations as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $s['id'] == $b['destination_station_id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-outline btn-sm">Scan QR</button>
                </form>
            </td>
        </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
