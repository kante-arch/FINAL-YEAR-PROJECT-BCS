<?php
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/auth.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT * FROM user WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password_hash"])) {
        $_SESSION["user_id"] = $user["id"];
        flash_set("Welcome back, {$user['full_name']}!", "success");
        header("Location: " . ($user["is_admin"] ? "admin/dashboard.php" : "dashboard.php"));
        exit;
    }

    flash_set("Invalid phone number or password.", "error");
    header("Location: login.php");
    exit;
}

$page_title = "Login";
require __DIR__ . "/includes/header.php";
?>
<section class="form-card">
    <h2>Log in</h2>
    <form method="POST">
        <label>Phone number
            <input type="text" name="phone" required>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn-primary">Log in</button>
    </form>
    <p class="switch-link">No account yet? <a href="register.php">Register</a></p>
    <p class="hint">Demo accounts &mdash; Passenger: 0700000002 / pass123 &nbsp;|&nbsp; Admin: 0700000001 / admin123</p>
</section>
<?php require __DIR__ . "/includes/footer.php"; ?>
