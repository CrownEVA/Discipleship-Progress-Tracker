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
    FROM email_change_requests
    WHERE token_hash = ?
      AND used_at IS NULL
      AND expires_at >= NOW()
    LIMIT 1
");
$stmt->execute([$tokenHash]);
$request = $stmt->fetch();

if (!$request) {
    die("Verification link is invalid or expired.");
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$request['new_email']]);
if ($stmt->fetch()) {
    die("That email is already in use.");
}

$stmt = $conn->prepare("
    UPDATE users
    SET email = ?
    WHERE id = ?
");
$stmt->execute([$request['new_email'], $request['user_id']]);

$stmt = $conn->prepare("
    UPDATE email_change_requests
    SET used_at = NOW()
    WHERE id = ?
");
$stmt->execute([$request['id']]);

$_SESSION["success"] = "Email updated successfully.";
header("Location: login.php");
exit;