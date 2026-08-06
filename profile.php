<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";
$user = require_login($pdo);

$page_title = "My Profile";
require __DIR__ . "/includes/header.php";
?>
<h2>My Profile</h2>
<div class="form-card profile-card">
    <div class="profile-row"><strong>Full name:</strong> <?= htmlspecialchars($user["full_name"]) ?></div>
    <div class="profile-row"><strong>Nationality:</strong> <?= htmlspecialchars($user["nationality"] ?? "-") ?></div>
    <div class="profile-row"><strong>ID / Passport number:</strong> <?= htmlspecialchars($user["identity_number"] ?? "-") ?></div>
    <div class="profile-row"><strong>Email:</strong> <?= htmlspecialchars($user["email"] ?? "-") ?></div>
    <div class="profile-row"><strong>Phone number:</strong> <?= htmlspecialchars($user["phone"] ?? "-") ?></div>
</div>
<?php require __DIR__ . "/includes/footer.php"; ?>
