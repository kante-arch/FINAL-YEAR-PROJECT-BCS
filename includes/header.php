<?php
/**
 * header.php
 * ----------
 * Included at the top of every page. Expects $pdo to already exist
 * and $page_title to optionally be set before including this file.
 */
require_once __DIR__ . "/auth.php";
$__user = current_user($pdo);
$page_title = $page_title ?? "SGR SeatFlow";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="<?= isset($__in_admin) ? '../assets/css/style.css' : 'assets/css/style.css' ?>">
</head>
<body>
    <nav class="navbar">
        <a href="<?= isset($__in_admin) ? '../index.php' : 'index.php' ?>" class="brand">SGR&nbsp;Seat<span>Flow</span></a>
        <div class="nav-links">
            <?php if ($__user): ?>
                <?php if ($__user["is_admin"]): ?>
                    <a href="<?= isset($__in_admin) ? 'dashboard.php' : 'admin/dashboard.php' ?>">Admin Dashboard</a>
                    <a href="<?= isset($__in_admin) ? 'sms_log.php' : 'admin/sms_log.php' ?>">SMS Log</a>
                <?php else: ?>
                    <a href="<?= isset($__in_admin) ? '../dashboard.php' : 'dashboard.php' ?>">My Tickets</a>
                    <a href="<?= isset($__in_admin) ? '../book.php' : 'book.php' ?>">Book a Seat</a>
                    <a href="<?= isset($__in_admin) ? '../profile.php' : 'profile.php' ?>">Profile</a>
                    <a href="<?= isset($__in_admin) ? '../wallet.php' : 'wallet.php' ?>">Wallet (<?= number_format($__user["wallet_balance"], 0) ?> TZS)</a>
                <?php endif; ?>
                <a href="<?= isset($__in_admin) ? '../logout.php' : 'logout.php' ?>" class="btn-outline">Logout</a>
            <?php else: ?>
                <a href="<?= isset($__in_admin) ? '../login.php' : 'login.php' ?>">Login</a>
                <a href="<?= isset($__in_admin) ? '../register.php' : 'register.php' ?>" class="btn-outline">Register</a>
            <?php endif; ?>
        </div>
    </nav>
    <main class="container">
        <?php flash_render(); ?>
