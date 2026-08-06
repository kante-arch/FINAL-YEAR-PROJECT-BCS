<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/notifications.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"]);
    $nationality = trim($_POST["nationality"]);
    $is_resident = isset($_POST["nationality"]) && $_POST["nationality"] === "Non-resident" ? 0 : 1;
    $identity_number = trim($_POST["identity_number"]);
    $phone = trim($_POST["phone"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if ($full_name === "" || $nationality === "" || $identity_number === "" || $phone === "" || $email === "") {
        flash_set("Please complete all profile fields before creating your account.", "error");
        header("Location: register.php");
        exit;
    }

    $phoneStmt = $pdo->prepare("SELECT id FROM user WHERE phone = ?");
    $phoneStmt->execute([$phone]);
    if ($phoneStmt->fetch()) {
        flash_set("That phone number is already registered.", "error");
        header("Location: register.php");
        exit;
    }

    $emailStmt = $pdo->prepare("SELECT id FROM user WHERE email = ?");
    $emailStmt->execute([$email]);
    if ($emailStmt->fetch()) {
        flash_set("That email address is already registered.", "error");
        header("Location: register.php");
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO user (full_name, nationality, is_resident, identity_number, phone, email, password_hash, is_admin, wallet_balance) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)")
        ->execute([$full_name, $nationality, $is_resident, $identity_number, $phone, $email, $hash]);

    $welcome_subject = "Welcome to SGR SeatFlow";
    $welcome_body = "
        <h2>Welcome to SGR SeatFlow</h2>
        <p>Hi {$full_name},</p>
        <p>Your account has been created successfully.</p>
        <p><strong>Full name:</strong> {$full_name}<br>
        <strong>Nationality:</strong> {$nationality}<br>
        <strong>Resident status:</strong> " . ($is_resident ? "Resident" : "Non-resident") . "<br>
        <strong>ID / Passport:</strong> {$identity_number}<br>
        <strong>Phone:</strong> {$phone}<br>
        <strong>Email:</strong> {$email}</p>
        <p>You can now log in and book your ticket.</p>
    ";
    send_email($email, $full_name, $welcome_subject, $welcome_body);

    flash_set("Account created! Please log in.", "success");
    header("Location: login.php");
    exit;
}

$page_title = "Register";
require __DIR__ . "/includes/header.php";
?>
<section class="form-card">
    <h2>Create your account</h2>
    <form method="POST">
        <label>Full name
            <input type="text" name="full_name" required>
        </label>
        <label>Nationality
            <select name="nationality" required>
                <option value="Resident">Resident</option>
                <option value="Non-resident">Non-resident</option>
            </select>
        </label>
        <label>ID / Passport number
            <input type="text" name="identity_number" required>
        </label>
        <label>Phone number
            <input type="text" name="phone" placeholder="07XXXXXXXX" required>
        </label>
        <label>Email address
            <input type="email" name="email" placeholder="you@example.com" required>
        </label>
        <label>Password
            <input type="password" name="password" required minlength="4">
        </label>
        <button type="submit" class="btn-primary">Register</button>
    </form>
    <p class="switch-link">Already have an account? <a href="login.php">Log in</a></p>
</section>
<?php require __DIR__ . "/includes/footer.php"; ?>
