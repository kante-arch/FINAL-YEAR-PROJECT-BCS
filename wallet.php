<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/notifications.php";
$user = require_login($pdo);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $amount = (float) ($_POST["amount"] ?? 0);
    if ($amount <= 0) {
        flash_set("Enter a valid top-up amount.", "error");
    } else {
        $pdo->prepare("UPDATE user SET wallet_balance = wallet_balance + ? WHERE id = ?")
            ->execute([$amount, $user["id"]]);
        $pdo->prepare("INSERT INTO transaction_log (user_id, amount, kind, note) VALUES (?, ?, 'topup', 'Wallet top-up (simulated mobile money)')")
            ->execute([$user["id"], $amount]);

        $fresh_stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
        $fresh_stmt->execute([$user["id"]]);
        $fresh_user = $fresh_stmt->fetch(PDO::FETCH_ASSOC);
        notify_wallet_topup($pdo, $fresh_user, $amount, $fresh_user["wallet_balance"]);

        flash_set("Wallet topped up by " . number_format($amount, 0) . " TZS.", "success");
    }
    header("Location: wallet.php");
    exit;
}

// Re-fetch fresh balance.
$user_stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
$user_stmt->execute([$user["id"]]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$tx_stmt = $pdo->prepare("SELECT * FROM transaction_log WHERE user_id = ? ORDER BY created_at DESC");
$tx_stmt->execute([$user["id"]]);
$transactions = $tx_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Wallet";
require __DIR__ . "/includes/header.php";
?>
<h2>My Wallet</h2>
<p class="balance">Current balance: <strong><?= number_format($user["wallet_balance"], 0) ?> TZS</strong></p>

<form method="POST" class="topup-form">
    <label>Top up amount (simulated mobile money)
        <input type="number" name="amount" min="1000" step="1000" placeholder="e.g. 20000" required>
    </label>
    <button type="submit" class="btn-primary">Top up</button>
</form>

<h3>Transaction history</h3>
<div class="table-wrap">
<table>
    <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Note</th></tr></thead>
    <tbody>
        <?php if (empty($transactions)): ?>
            <tr><td colspan="4">No transactions yet.</td></tr>
        <?php else: foreach ($transactions as $t): ?>
        <tr>
            <td><?= date("d M Y, H:i", strtotime($t["created_at"])) ?></td>
            <td><span class="badge badge-<?= htmlspecialchars($t["kind"]) ?>"><?= htmlspecialchars($t["kind"]) ?></span></td>
            <td class="<?= $t['amount'] > 0 ? 'amount-pos' : 'amount-neg' ?>">
                <?= ($t["amount"] > 0 ? '+' : '') . number_format($t["amount"], 0) ?> TZS
            </td>
            <td><?= htmlspecialchars($t["note"]) ?></td>
        </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . "/includes/footer.php"; ?>
