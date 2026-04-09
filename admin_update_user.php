<?php
session_start();
include 'database.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: login.php");
    exit;
}

$userId = (int) ($_POST["user_id"] ?? 0);
$firstName = trim($_POST["first_name"] ?? '');
$lastName = trim($_POST["last_name"] ?? '');
$email = trim($_POST["email"] ?? '');
$role = $_POST["role"] ?? 'user';
$points = (int) ($_POST["points"] ?? 0);

$stmt = $conn->prepare("
    UPDATE users
    SET first_name = ?, last_name = ?, email = ?, role = ?, points = ?
    WHERE id = ?
");
$stmt->execute([$firstName, $lastName, $email, $role, $points, $userId]);

header("Location: home.php");
exit;