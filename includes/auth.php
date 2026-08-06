<?php
/**
 * auth.php
 * --------
 * Small helper functions for login sessions. Include this at the
 * top of any page that needs to know who is logged in.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(PDO $pdo) {
    if (!isset($_SESSION["user_id"])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$_SESSION["user_id"]]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

/** Redirect to login if nobody is logged in. Pass true to also require admin. */
function require_login(PDO $pdo, bool $admin_only = false) {
    $user = current_user($pdo);
    if (!$user) {
        header("Location: login.php");
        exit;
    }
    if ($admin_only && !$user["is_admin"]) {
        header("Location: dashboard.php");
        exit;
    }
    return $user;
}

function flash_set(string $message, string $category = "success") {
    $_SESSION["flash"][] = ["message" => $message, "category" => $category];
}

function flash_render() {
    if (empty($_SESSION["flash"])) return;

    echo '<div class="toast-container" aria-live="polite" aria-atomic="true">';
    foreach ($_SESSION["flash"] as $f) {
        echo '<div class="toast toast-' . htmlspecialchars($f["category"]) . '" role="status">'
            . '<span class="toast-message">' . htmlspecialchars($f["message"]) . '</span>'
            . '<button type="button" class="toast-close" aria-label="Close notification">&times;</button>'
            . '</div>';
    }
    echo '</div>';

    unset($_SESSION["flash"]);
}
