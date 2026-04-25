<?php
session_start();
include 'database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$journeyId = (int) ($_POST["journey_id"] ?? 0);
$lessonId = (int) ($_POST["lesson_id"] ?? 0);
$action = $_POST["action"] ?? '';

if ($journeyId <= 0 || $lessonId <= 0 || !in_array($action, ['complete', 'undo'], true)) {
    header("Location: journeys.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id, journey_id, lesson
    FROM lessons
    WHERE id = ? AND journey_id = ?
");
$stmt->execute([$lessonId, $journeyId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header("Location: content.php?journey_id=" . $journeyId);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, status
    FROM user_journeys
    WHERE user_id = ? AND journey_id = ?
");
$stmt->execute([$userId, $journeyId]);
$enrollment = $stmt->fetch();

if (!$enrollment || $enrollment['status'] === 'unenrolled') {
    header("Location: journeys.php");
    exit;
}

function recalcJourneyProgress(PDO $conn, int $userId, int $journeyId): void
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total_lessons
        FROM lessons
        WHERE journey_id = ?
    ");
    $stmt->execute([$journeyId]);
    $total = (int) $stmt->fetch()['total_lessons'];

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS completed_lessons
        FROM user_lesson_progress ulp
        INNER JOIN lessons l ON l.id = ulp.lesson_id
        WHERE ulp.user_id = ?
          AND l.journey_id = ?
          AND ulp.is_completed = 1
    ");
    $stmt->execute([$userId, $journeyId]);
    $completed = (int) $stmt->fetch()['completed_lessons'];

    $progressPercent = $total > 0 ? (int) floor(($completed / $total) * 100) : 0;

    $status = 'enrolled';
    $completedAt = 'NULL';

    if ($completed > 0) {
        $status = 'in_progress';
    }

    if ($total > 0 && $completed >= $total) {
        $status = 'completed';
        $completedAt = 'NOW()';
    }

    $sql = "
        UPDATE user_journeys
        SET progress_percent = ?,
            status = ?,
            updated_at = NOW()
    ";

    if ($status === 'completed') {
        $sql .= ", completed_at = NOW()";
    } else {
        $sql .= ", completed_at = NULL";
    }

    $sql .= " WHERE user_id = ? AND journey_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$progressPercent, $status, $userId, $journeyId]);
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT is_completed
        FROM user_lesson_progress
        WHERE user_id = ? AND lesson_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$userId, $lessonId]);
    $existing = $stmt->fetch();
    $wasCompleted = $existing && (int) $existing['is_completed'] === 1;

    if ($action === 'complete') {
        if ((int) $lesson['lesson'] > 1) {
            $prevLessonNumber = (int) $lesson['lesson'] - 1;

            $stmt = $conn->prepare("
                SELECT l.id
                FROM lessons l
                INNER JOIN user_lesson_progress ulp ON ulp.lesson_id = l.id
                WHERE l.journey_id = ?
                  AND l.lesson = ?
                  AND ulp.user_id = ?
                  AND ulp.is_completed = 1
                LIMIT 1
            ");
            $stmt->execute([$journeyId, $prevLessonNumber, $userId]);

            if (!$stmt->fetch()) {
                $conn->rollBack();
                header("Location: content.php?journey_id=" . $journeyId . "&lesson_id=" . $lessonId);
                exit;
            }
        }

        if (!$existing) {
            $stmt = $conn->prepare("
                INSERT INTO user_lesson_progress
                (user_id, lesson_id, is_completed, completed_at, created_at, updated_at)
                VALUES (?, ?, 1, NOW(), NOW(), NOW())
            ");
            $stmt->execute([$userId, $lessonId]);

            $stmt = $conn->prepare("
                UPDATE users
                SET points = points + 10
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $_SESSION['points_flash'] = 10;
        } elseif ((int) $existing['is_completed'] === 0) {
            $stmt = $conn->prepare("
                UPDATE user_lesson_progress
                SET is_completed = 1,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE user_id = ? AND lesson_id = ?
            ");
            $stmt->execute([$userId, $lessonId]);

            if (!$wasCompleted) {
                $stmt = $conn->prepare("
                    UPDATE users
                    SET points = points + 10
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                $_SESSION['points_flash'] = 10;
            }
        }

        $stmt = $conn->prepare("
            SELECT j.title AS journey_title, l.title AS lesson_title
            FROM lessons l
            INNER JOIN journeys j ON j.id = l.journey_id
            WHERE l.id = ?
        ");
        $stmt->execute([$lessonId]);
        $row = $stmt->fetch();

        $journeyTitle = $row['journey_title'] ?? 'your journey';
        $lessonTitle = $row['lesson_title'] ?? 'a lesson';

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, journey_id, lesson_id, type, title, message, is_read, created_at, updated_at)
            VALUES (?, ?, ?, 'points_earned', '+10 Points Earned!', ?, 0, NOW(), NOW())
        ");
        $stmt->execute([
            $userId,
            $journeyId,
            $lessonId,
            "You earned 10 points for completing {$lessonTitle} in {$journeyTitle}."
        ]);
    }

    if ($action === 'undo') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS later_completed
            FROM lessons l
            INNER JOIN user_lesson_progress ulp ON ulp.lesson_id = l.id
            WHERE l.journey_id = ?
              AND l.lesson > ?
              AND ulp.user_id = ?
              AND ulp.is_completed = 1
        ");
        $stmt->execute([$journeyId, (int) $lesson['lesson'], $userId]);
        $laterCompleted = (int) $stmt->fetch()['later_completed'];

        if ($laterCompleted > 0) {
            $conn->rollBack();
            header("Location: content.php?journey_id=" . $journeyId . "&lesson_id=" . $lessonId);
            exit;
        }

        if ($existing && (int) $existing['is_completed'] === 1) {
            $stmt = $conn->prepare("
                UPDATE user_lesson_progress
                SET is_completed = 0,
                    completed_at = NULL,
                    updated_at = NOW()
                WHERE user_id = ? AND lesson_id = ?
            ");
            $stmt->execute([$userId, $lessonId]);

            $stmt = $conn->prepare("
                UPDATE users
                SET points = GREATEST(points - 10, 0)
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        }

        $stmt = $conn->prepare("
            SELECT j.title AS journey_title, l.title AS lesson_title
            FROM lessons l
            INNER JOIN journeys j ON j.id = l.journey_id
            WHERE l.id = ?
        ");
        $stmt->execute([$lessonId]);
        $row = $stmt->fetch();

        $journeyTitle = $row['journey_title'] ?? 'your journey';
        $lessonTitle = $row['lesson_title'] ?? 'a lesson';

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, journey_id, lesson_id, type, title, message, is_read, created_at, updated_at)
            VALUES (?, ?, ?, 'points_lost', '-10 Points Lost', ?, 0, NOW(), NOW())
        ");
        $stmt->execute([
            $userId,
            $journeyId,
            $lessonId,
            "You lost 10 points after undoing completion of {$lessonTitle} in {$journeyTitle}."
        ]);
    }

    recalcJourneyProgress($conn, $userId, $journeyId);

    $stmt = $conn->prepare("
        SELECT lesson
        FROM lessons
        WHERE id = ? AND journey_id = ?
    ");
    $stmt->execute([$lessonId, $journeyId]);
    $lessonRow = $stmt->fetch();

    $conn->commit();

    header("Location: content.php?journey_id=" . $journeyId . "&lesson_id=" . $lessonId);
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    die("Error: " . $e->getMessage());
}