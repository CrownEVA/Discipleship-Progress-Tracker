<?php
session_start();
include 'links.php';
include 'database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: journeys.php");
    exit;
}

$journeyId = (int) ($_GET["journey_id"] ?? 0);
if ($journeyId <= 0) {
    header("Location: journeys.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT id, title, description, image
    FROM journeys
    WHERE id = ?
");
$stmt->execute([$journeyId]);
$journey = $stmt->fetch();

if (!$journey) {
    header("Location: journeys.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];

    try {
        if ($action === "add_lesson") {
            $lessonNum = (int) ($_POST["lesson"] ?? 0);
            $title = trim($_POST["title"] ?? '');
            $content = trim($_POST["content"] ?? '');

            if ($lessonNum > 0 && $title !== '' && $content !== '') {
                $stmt = $conn->prepare("
                    INSERT INTO lessons (journey_id, lesson, title, content)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$journeyId, $lessonNum, $title, $content]);
            }

            header("Location: manage_lessons.php?journey_id=" . $journeyId);
            exit;
        }

        if ($action === "update_lesson") {
            $lessonId = (int) ($_POST["lesson_id"] ?? 0);
            $lessonNum = (int) ($_POST["lesson"] ?? 0);
            $title = trim($_POST["title"] ?? '');
            $content = trim($_POST["content"] ?? '');

            if ($lessonId > 0 && $lessonNum > 0 && $title !== '' && $content !== '') {
                $stmt = $conn->prepare("
                    UPDATE lessons
                    SET lesson = ?, title = ?, content = ?
                    WHERE id = ? AND journey_id = ?
                ");
                $stmt->execute([$lessonNum, $title, $content, $lessonId, $journeyId]);
            }

            header("Location: manage_lessons.php?journey_id=" . $journeyId);
            exit;
        }

        if ($action === "delete_lesson") {
            $lessonId = (int) ($_POST["lesson_id"] ?? 0);

            if ($lessonId > 0) {
                $conn->beginTransaction();

                $stmt = $conn->prepare("
                    DELETE FROM user_lesson_progress
                    WHERE lesson_id = ?
                ");
                $stmt->execute([$lessonId]);

                $stmt = $conn->prepare("
                    DELETE FROM lessons
                    WHERE id = ? AND journey_id = ?
                ");
                $stmt->execute([$lessonId, $journeyId]);

                $conn->commit();
            }

            header("Location: manage_lessons.php?journey_id=" . $journeyId);
            exit;
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        die("Lesson action failed: " . $e->getMessage());
    }
}

$stmt = $conn->prepare("
    SELECT id, journey_id, lesson, title, content
    FROM lessons
    WHERE journey_id = ?
    ORDER BY lesson ASC, id ASC
");
$stmt->execute([$journeyId]);
$lessons = $stmt->fetchAll();

$nextLessonNumber = 1;
if (!empty($lessons)) {
    $numbers = array_map(fn($l) => (int) $l['lesson'], $lessons);
    $nextLessonNumber = max($numbers) + 1;
}
?>

<header>
    <nav class="sticky-top bg-white py-3">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="journeys.php">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <h5 class="m-0">Manage Lessons</h5>
                <div></div>
            </div>
        </div>
    </nav>
</header>

<body class="bg-body-tertiary">
    <div class="container py-4">
        <div class="border rounded-3 border-primary bg-white p-3 mb-4">
            <div class="d-flex gap-3 align-items-center">
                <img src="<?= htmlspecialchars($journey['image']) ?>" alt="Journey" class="rounded-3" style="width: 80px; height: 80px; object-fit: cover;">
                <div>
                    <h5 class="mb-1"><?= htmlspecialchars($journey['title']) ?></h5>
                    <p class="mb-0 text-muted"><?= htmlspecialchars($journey['description']) ?></p>
                </div>
            </div>
        </div>

        <div class="border rounded-3 border-primary bg-white p-4 mb-4">
            <h5 class="mb-3">Add Lesson</h5>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_lesson">

                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Lesson No</label>
                        <input type="number" name="lesson" class="form-control" min="1" value="<?= (int) $nextLessonNumber ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Content</label>
                        <input type="text" name="content" class="form-control" required>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Add Lesson</button>
                </div>
            </form>
        </div>

        <div class="border rounded-3 border-primary bg-white p-4">
            <h5 class="mb-3">Lessons</h5>

            <?php if (empty($lessons)): ?>
                <div class="alert alert-light border mb-0">No lessons yet.</div>
            <?php else: ?>
                <?php foreach ($lessons as $lesson): ?>
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="w-100">
                                <h6 class="mb-1">Lesson <?= (int) $lesson['lesson'] ?></h6>
                                <strong><?= htmlspecialchars($lesson['title']) ?></strong>
                                <p class="mb-0 text-muted small"><?= htmlspecialchars($lesson['content']) ?></p>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-warning btn-sm" onclick="toggleEditRow(<?= (int) $lesson['id'] ?>)">
                                    Edit
                                </button>

                                <form method="POST" action="" class="m-0" onsubmit="return confirm('Delete this lesson?');">
                                    <input type="hidden" name="action" value="delete_lesson">
                                    <input type="hidden" name="lesson_id" value="<?= (int) $lesson['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </div>

                        <div id="editRow<?= (int) $lesson['id'] ?>" class="mt-3 d-none">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="update_lesson">
                                <input type="hidden" name="lesson_id" value="<?= (int) $lesson['id'] ?>">

                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label">Lesson No</label>
                                        <input type="number" name="lesson" class="form-control" min="1" value="<?= (int) $lesson['lesson'] ?>" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($lesson['title'], ENT_QUOTES) ?>" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Content</label>
                                        <input type="text" name="content" class="form-control" value="<?= htmlspecialchars($lesson['content'], ENT_QUOTES) ?>" required>
                                    </div>
                                </div>

                                <div class="mt-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                    <button type="button" class="btn btn-secondary" onclick="toggleEditRow(<?= (int) $lesson['id'] ?>)">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

<script>
function toggleEditRow(id) {
    const row = document.getElementById('editRow' + id);
    row.classList.toggle('d-none');
}
</script>
</body>