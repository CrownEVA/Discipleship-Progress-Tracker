<?php
session_start();
include 'database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$journeyId = (int) ($_POST["journey_id"] ?? 0);

if ($journeyId <= 0) {
    header("Location: journeys.php");
    exit;
}

$stmt = $conn->prepare("SELECT id FROM journeys WHERE id = ?");
$stmt->execute([$journeyId]);

if (!$stmt->fetch()) {
    header("Location: journeys.php");
    exit;
}

$stmt = $conn->prepare("SELECT id, status FROM user_journeys WHERE user_id = ? AND journey_id = ?");
$stmt->execute([$userId, $journeyId]);
$existing = $stmt->fetch();

if (!$existing) {
    $stmt = $conn->prepare("
        INSERT INTO user_journeys
        (user_id, journey_id, status, progress_percent, enrolled_at, created_at, updated_at)
        VALUES (?, ?, 'enrolled', 0, NOW(), NOW(), NOW())
    ");
    $stmt->execute([$userId, $journeyId]);
} else {
    $stmt = $conn->prepare("
        UPDATE user_journeys
        SET status = 'enrolled',
            updated_at = NOW()
        WHERE user_id = ? AND journey_id = ?
    ");
    $stmt->execute([$userId, $journeyId]);
}

$stmt = $conn->prepare("SELECT title FROM journeys WHERE id = ?");
$stmt->execute([$journeyId]);
$journey = $stmt->fetch();
$journeyTitle = $journey ? $journey['title'] : 'your journey';

$stmt = $conn->prepare("
    INSERT INTO notifications (user_id, journey_id, type, title, message, is_read, created_at, updated_at)
    VALUES (?, ?, 'journey_started', 'Journey Started', ?, 0, NOW(), NOW())
");
$stmt->execute([
    $userId,
    $journeyId,
    "You've enrolled in {$journeyTitle}. Start your journey now!"
]);

header("Location: content.php?journey_id=" . $journeyId);
exit;