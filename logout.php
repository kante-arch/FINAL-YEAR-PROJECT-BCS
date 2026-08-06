<?php
require_once __DIR__ . "/includes/auth.php";
$_SESSION = [];
session_destroy();
session_start();
flash_set("You have been logged out.", "success");
header("Location: index.php");
exit;
