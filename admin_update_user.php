<?php
session_start();
date_default_timezone_set('Asia/Manila');

include 'database.php';
include 'mailer.php';

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_role"] ?? '') !== "admin") {
    header("Location: login.php");
    exit;
}

$userId = (int) ($_POST["user_id"] ?? 0);
$firstName = trim($_POST["first_name"] ?? '');
$lastName = trim($_POST["last_name"] ?? '');
$email = trim($_POST["email"] ?? '');
$role = $_POST["role"] ?? 'user';
$points = (int) ($_POST["points"] ?? 0);

$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$oldUser = $stmt->fetch();

if (!$oldUser) {
    header("Location: home.php");
    exit;
}

$conn->beginTransaction();

$stmt = $conn->prepare("
    UPDATE users
    SET first_name = ?, last_name = ?, role = ?, points = ?
    WHERE id = ?
");
$stmt->execute([$firstName, $lastName, $role, $points, $userId]);

if ($email !== $oldUser['email']) {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt = $conn->prepare("
        INSERT INTO email_change_requests (user_id, new_email, token_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $email, $tokenHash, $expiresAt]);

    $verifyLink = "http://localhost/Discipleship-Progress-Tracker/verify_email_change.php?token=" . urlencode($token);
    $subject = "Verify your new email";
    $html = "
        <p>Hello,</p>
        <p>Please verify your new email address by clicking the link below.</p>
        <p><a href='{$verifyLink}'>Verify Email Change</a></p>
        <p>This link expires in 24 hours.</p>
    ";

    sendMail($email, $subject, $html);
}

$conn->commit();
header("Location: home.php");
exit;