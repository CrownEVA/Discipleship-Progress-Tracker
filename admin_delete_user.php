<?php
session_start();
include 'database.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: login.php");
    exit;
}

$userId = (int) ($_POST["user_id"] ?? 0);

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$userId]);

header("Location: home.php");
exit;