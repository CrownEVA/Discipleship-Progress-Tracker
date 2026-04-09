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

$stmt = $conn->prepare("
    DELETE FROM user_journeys
    WHERE user_id = ? AND journey_id = ?
");
$stmt->execute([$userId, $journeyId]);

$stmt = $conn->prepare("
    DELETE ulp
    FROM user_lesson_progress ulp
    INNER JOIN lessons l ON l.id = ulp.lesson_id
    WHERE ulp.user_id = ?
      AND l.journey_id = ?
");
$stmt->execute([$userId, $journeyId]);

header("Location: journeys.php");
exit;
?>