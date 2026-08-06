<?php
$__in_admin = true;
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/auth.php";
require_login($pdo, true);

$stmt = $pdo->query("SELECT * FROM sms_log ORDER BY created_at DESC LIMIT 100");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Simulated SMS Log";
require __DIR__ . "/../includes/header.php";
?>
<h2>Simulated SMS Outbox</h2>
<p class="hint">Real SMS sending needs a paid gateway account (e.g. Beem Africa, Africa's Talking)
   with an API key. Until you add one, every "SMS" the system would have sent is logged here instead,
   so you can demonstrate the notification logic works end-to-end.</p>

<div class="table-wrap">
<table>
    <thead><tr><th>Date</th><th>Phone</th><th>Message</th></tr></thead>
    <tbody>
        <?php if (empty($logs)): ?>
            <tr><td colspan="3">No simulated SMS sent yet.</td></tr>
        <?php else: foreach ($logs as $l): ?>
        <tr>
            <td><?= date("d M Y, H:i", strtotime($l["created_at"])) ?></td>
            <td><?= htmlspecialchars($l["phone"]) ?></td>
            <td><?= htmlspecialchars($l["message"]) ?></td>
        </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
