<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/seat_logic.php";
$user = require_login($pdo);

$stmt = $pdo->prepare("
    SELECT b.*, s.coach_number, s.seat_number,
           os.name AS origin_name, os.order_index AS origin_order,
           ds.name AS destination_name, ds.order_index AS destination_order,
           ex.name AS exit_name
    FROM booking b
    JOIN seat s ON s.id = b.seat_id
    JOIN station os ON os.id = b.origin_station_id
    JOIN station ds ON ds.id = b.destination_station_id
    LEFT JOIN station ex ON ex.id = b.actual_exit_station_id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$user["id"]]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$all_stations = stations_in_order($pdo);

$page_title = "My Tickets";
require __DIR__ . "/includes/header.php";
?>
<h2>Welcome, <?= htmlspecialchars($user["full_name"]) ?></h2>
<p><a href="book.php" class="btn-primary">+ Book a new seat</a></p>

<div class="table-wrap">
<table>
    <thead><tr><th>Route</th><th>Seat</th><th>Fare Paid</th><th>Status</th><th></th><th>Exiting early?</th></tr></thead>
    <tbody>
        <?php if (empty($bookings)): ?>
            <tr><td colspan="6">No bookings yet. Book your first seat!</td></tr>
        <?php else: foreach ($bookings as $b): ?>
        <tr>
            <td><?= htmlspecialchars($b["origin_name"]) ?> &rarr; <?= htmlspecialchars($b["destination_name"]) ?></td>
            <td><?= htmlspecialchars("Coach " . $b["coach_number"] . " - " . $b["seat_number"]) ?></td>
            <td><?= number_format($b["fare_paid"], 0) ?> TZS</td>
            <td>
                <span class="badge badge-<?= htmlspecialchars($b["status"]) ?>">
                    <?= htmlspecialchars($b["status"]) ?>
                    <?php if ($b["status"] === "exited" && $b["exit_name"]): ?> at <?= htmlspecialchars($b["exit_name"]) ?><?php endif; ?>
                </span>
            </td>
            <td><a href="ticket.php?id=<?= $b['id'] ?>">View ticket</a></td>
            <td>
                <?php if ($b["status"] === "active"): ?>
                    <form method="POST" action="exit.php" class="inline-form">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <select name="station_id" required>
                            <option value="">I'm currently at...</option>
                            <?php foreach ($all_stations as $s): ?>
                                <?php if ($s["order_index"] > $b["origin_order"] && $s["order_index"] <= $b["destination_order"]): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-outline btn-sm"
                            onclick="return confirm('Confirm you are exiting the train now? This will end your ticket and refund any unused distance.');">
                            Exit now
                        </button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . "/includes/footer.php"; ?>
