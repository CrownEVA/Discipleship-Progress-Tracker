<?php
session_start();
$pointsFlash = $_SESSION['points_flash'] ?? null;
unset($_SESSION['points_flash']);

include 'links.php';
include 'database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$journeyId = (int) ($_GET["journey_id"] ?? 0);
$requestedLessonId = (int) ($_GET["lesson_id"] ?? 0);

if ($journeyId <= 0) {
    header("Location: journeys.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT j.id, j.title, j.description, j.image,
           uj.status, uj.progress_percent
    FROM journeys j
    LEFT JOIN user_journeys uj ON uj.journey_id = j.id AND uj.user_id = ?
    WHERE j.id = ?
");
$stmt->execute([$userId, $journeyId]);
$journey = $stmt->fetch();

if (!$journey) {
    header("Location: journeys.php");
    exit;
}

if (!$journey['status'] || $journey['status'] === 'unenrolled') {
    header("Location: journeys.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id, journey_id, lesson, title, content
    FROM lessons
    WHERE journey_id = ?
    ORDER BY lesson ASC
");
$stmt->execute([$journeyId]);
$lessons = $stmt->fetchAll();

if (empty($lessons)) {
    die("No lessons found for this journey.");
}

$currentLesson = null;

if ($requestedLessonId > 0) {
    foreach ($lessons as $lesson) {
        if ((int) $lesson['id'] === $requestedLessonId) {
            $currentLesson = $lesson;
            break;
        }
    }
}

if (!$currentLesson) {
    $stmt = $conn->prepare("
        SELECT l.id, l.journey_id, l.lesson, l.title, l.content
        FROM lessons l
        LEFT JOIN user_lesson_progress ulp
            ON ulp.lesson_id = l.id
           AND ulp.user_id = ?
           AND ulp.is_completed = 1
        WHERE l.journey_id = ?
        ORDER BY
            CASE WHEN ulp.id IS NULL THEN 0 ELSE 1 END,
            l.lesson ASC
        LIMIT 1
    ");
    $stmt->execute([$userId, $journeyId]);
    $currentLesson = $stmt->fetch();

    if (!$currentLesson) {
        $currentLesson = $lessons[0];
    }
}

$currentLessonId = (int) $currentLesson['id'];

$stmt = $conn->prepare("
    SELECT is_completed
    FROM user_lesson_progress
    WHERE user_id = ? AND lesson_id = ?
");
$stmt->execute([$userId, $currentLessonId]);
$progressRow = $stmt->fetch();
$isCompleted = $progressRow && (int) $progressRow['is_completed'] === 1;

$previousLesson = null;
$nextLesson = null;

foreach ($lessons as $index => $lesson) {
    if ((int) $lesson['id'] === $currentLessonId) {
        if ($index > 0) {
            $previousLesson = $lessons[$index - 1];
        }
        if ($index < count($lessons) - 1) {
            $nextLesson = $lessons[$index + 1];
        }
        break;
    }
}

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_lessons
    FROM lessons
    WHERE journey_id = ?
");
$stmt->execute([$journeyId]);
$totalLessons = (int) $stmt->fetch()['total_lessons'];

$stmt = $conn->prepare("
    SELECT COUNT(*) AS completed_lessons
    FROM user_lesson_progress
    WHERE user_id = ?
      AND lesson_id IN (
          SELECT id FROM lessons WHERE journey_id = ?
      )
      AND is_completed = 1
");
$stmt->execute([$userId, $journeyId]);
$completedLessons = (int) $stmt->fetch()['completed_lessons'];

$progressPercent = $totalLessons > 0 ? (int) floor(($completedLessons / $totalLessons) * 100) : 0;
?>

<header>
    <nav class="sticky-top bg-white py-3">
        <div class="container">
            <div class="align-items-center d-flex justify-content-between gap-3">
                <a href="journeys.php">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>

                <h5 class="m-0 text-center flex-grow-1">
                    <?= htmlspecialchars($journey['title']) ?>
                </h5>

                <div class="d-flex gap-2">
                    <?php if ($previousLesson): ?>
                        <a class="btn btn-outline-primary" href="content.php?journey_id=<?= $journeyId ?>&lesson_id=<?= (int) $previousLesson['id'] ?>">Previous</a>
                    <?php endif; ?>

                    <?php if ($isCompleted && $nextLesson): ?>
                        <a class="btn btn-primary" href="content.php?journey_id=<?= $journeyId ?>&lesson_id=<?= (int) $nextLesson['id'] ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>

<body class="bg-body-tertiary">
    <div class="container p-4 border border-primary rounded-3 bg-white my-4">
        <div class="mb-3 bg-primary-subtle p-3 rounded-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold">Journey progress</span>
                <span class="fw-semibold text-primary"><?= $progressPercent ?>%</span>
            </div>
            <div class="progress mb-1" style="height: 7px">
                <div class="progress-bar" style="width: <?= $progressPercent ?>%"></div>
            </div>
            <small><?= $completedLessons ?> of <?= $totalLessons ?> lessons complete</small>
        </div>

        <div class="my-3">
            <h4>Lesson <?= (int) $currentLesson['lesson'] ?></h4>
            <i><?= htmlspecialchars($currentLesson['title']) ?></i>
        </div>

        <div>
            <h5>Content</h5>
            <p class="border rounded-3 p-3 bg-white">
                <?= nl2br(htmlspecialchars($currentLesson['content'])) ?>
            </p>
        </div>

        <?php if (!$isCompleted): ?>
            <div class="rounded-3 p-3 d-flex justify-content-between align-items-center bg-primary-subtle">
                <p class="m-0 p-0">Mark this step as complete to continue</p>
                <form method="POST" action="lesson_toggle.php" class="m-0">
                    <input type="hidden" name="journey_id" value="<?= $journeyId ?>">
                    <input type="hidden" name="lesson_id" value="<?= $currentLessonId ?>">
                    <input type="hidden" name="action" value="complete">
                    <button class="btn btn-primary">Mark Done</button>
                </form>
            </div>
        <?php else: ?>
            <div class="rounded-3 p-3 d-flex justify-content-between align-items-center bg-success-subtle">
                <p class="m-0 p-0">Step Complete</p>
                <form method="POST" action="lesson_toggle.php" class="m-0">
                    <input type="hidden" name="journey_id" value="<?= $journeyId ?>">
                    <input type="hidden" name="lesson_id" value="<?= $currentLessonId ?>">
                    <input type="hidden" name="action" value="undo">
                    <button class="btn btn-success">Undo</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pointsFlash): ?>
    <div class="modal fade" id="pointsEarnedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-success">Well done</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    You earned <?= (int) $pointsFlash ?> points for completing this lesson.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">Great</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = new bootstrap.Modal(document.getElementById('pointsEarnedModal'));
        modal.show();
    });
    </script>
    <?php endif; ?>
</body>