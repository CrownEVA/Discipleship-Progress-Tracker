<?php
session_start();
date_default_timezone_set('Asia/Manila');

include 'database.php';

$token = $_GET['token'] ?? '';
if ($token === '') {
    die("Invalid verification link.");
}

$tokenHash = hash('sha256', $token);

$stmt = $conn->prepare("
    SELECT *
    FROM pending_registrations
    WHERE token_hash = ?
      AND expires_at >= NOW()
    LIMIT 1
");
$stmt->execute([$tokenHash]);
$pending = $stmt->fetch();

if (!$pending) {
    die("Verification link is invalid or expired.");
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$pending['email']]);
if ($stmt->fetch()) {
    $stmt = $conn->prepare("DELETE FROM pending_registrations WHERE id = ?");
    $stmt->execute([$pending['id']]);
    die("Email is already registered.");
}

$stmt = $conn->prepare("
    INSERT INTO users (first_name, last_name, email, password, role, points)
    VALUES (?, ?, ?, ?, 'user', 0)
");
$stmt->execute([
    $pending['first_name'],
    $pending['last_name'],
    $pending['email'],
    $pending['password_hash']
]);

$stmt = $conn->prepare("DELETE FROM pending_registrations WHERE id = ?");
$stmt->execute([$pending['id']]);

$_SESSION['success'] = "Email verified. You can now log in.";
header("Location: login.php");
exit;