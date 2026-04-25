<?php
session_start();
include 'links.php';
include 'database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$isAdmin = isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin";

$search = trim($_GET["q"] ?? '');
$searchLike = '%' . $search . '%';

date_default_timezone_set('Asia/Manila');

function getLevelInfo(int $points): array
{
    $levels = [
        ['name' => 'New Believer', 'min' => 0],
        ['name' => 'Growing Disciple', 'min' => 100],
        ['name' => 'Faith Builder', 'min' => 250],
        ['name' => 'Servant Leader', 'min' => 500],
        ['name' => 'Kingdom Worker', 'min' => 1000],
        ['name' => 'Disciple Maker', 'min' => 2000],
        ['name' => 'Spiritual Mentor', 'min' => 4000],
    ];

    $current = $levels[0];
    $next = null;

    foreach ($levels as $index => $level) {
        if ($points >= $level['min']) {
            $current = $level;
            $next = $levels[$index + 1] ?? null;
        }
    }

    $progress = 100;
    $pointsToNext = 0;

    if ($next) {
        $range = $next['min'] - $current['min'];
        $earned = $points - $current['min'];
        $progress = $range > 0 ? (int) floor(($earned / $range) * 100) : 100;
        $progress = max(0, min(100, $progress));
        $pointsToNext = max(0, $next['min'] - $points);
    }

    return [
        'name' => $current['name'],
        'min' => $current['min'],
        'next_name' => $next['name'] ?? null,
        'next_min' => $next['min'] ?? null,
        'progress_to_next' => $progress,
        'points_to_next' => $pointsToNext,
    ];
}

$uploadDir = 'uploads/journeys/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function saveJourneyImage(array $file, string $uploadDir): ?string
{
    if (!isset($file['tmp_name']) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed, true)) {
        return null;
    }

    $newName = uniqid('journey_', true) . '.' . $ext;
    $target = $uploadDir . $newName;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        return $target;
    }

    return null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $isAdmin && isset($_POST["action"])) {
    $action = $_POST["action"];

    try {
        if ($action === "create_journey") {
            $title = trim($_POST["title"] ?? '');
            $description = trim($_POST["description"] ?? '');
            $imagePath = saveJourneyImage($_FILES["image"] ?? [], $uploadDir);

            if ($title !== '' && $description !== '' && $imagePath) {
                $stmt = $conn->prepare("
                    INSERT INTO journeys (title, description, image)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$title, $description, $imagePath]);
            }

            header("Location: journeys.php");
            exit;
        }

        if ($action === "update_journey") {
            $journeyId = (int) ($_POST["journey_id"] ?? 0);
            $title = trim($_POST["title"] ?? '');
            $description = trim($_POST["description"] ?? '');

            $stmt = $conn->prepare("SELECT image FROM journeys WHERE id = ?");
            $stmt->execute([$journeyId]);
            $existingJourney = $stmt->fetch();

            if ($journeyId > 0 && $title !== '' && $description !== '' && $existingJourney) {
                $imagePath = $existingJourney['image'];

                if (!empty($_FILES["image"]["name"])) {
                    $newImage = saveJourneyImage($_FILES["image"], $uploadDir);
                    if ($newImage) {
                        $imagePath = $newImage;
                    }
                }

                $stmt = $conn->prepare("
                    UPDATE journeys
                    SET title = ?, description = ?, image = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $imagePath, $journeyId]);
            }

            header("Location: journeys.php");
            exit;
        }

        if ($action === "delete_journey") {
            $journeyId = (int) ($_POST["journey_id"] ?? 0);

            if ($journeyId > 0) {
                $conn->beginTransaction();

                $stmt = $conn->prepare("
                    DELETE ulp
                    FROM user_lesson_progress ulp
                    INNER JOIN lessons l ON l.id = ulp.lesson_id
                    WHERE l.journey_id = ?
                ");
                $stmt->execute([$journeyId]);

                $stmt = $conn->prepare("DELETE FROM lessons WHERE journey_id = ?");
                $stmt->execute([$journeyId]);

                $stmt = $conn->prepare("DELETE FROM user_journeys WHERE journey_id = ?");
                $stmt->execute([$journeyId]);

                $stmt = $conn->prepare("DELETE FROM journeys WHERE id = ?");
                $stmt->execute([$journeyId]);

                $conn->commit();
            }

            header("Location: journeys.php");
            exit;
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        die("Journey action failed: " . $e->getMessage());
    }
}

if ($isAdmin) {
    $stmt = $conn->prepare("
        SELECT
            j.id,
            j.image,
            j.title,
            j.description,
            COUNT(DISTINCT l.id) AS lesson_count,
            COUNT(DISTINCT uj_all.user_id) AS enrolled_users
        FROM journeys j
        LEFT JOIN lessons l ON l.journey_id = j.id
        LEFT JOIN user_journeys uj_all ON uj_all.journey_id = j.id
        WHERE (j.title LIKE ? OR j.description LIKE ?)
        GROUP BY j.id, j.image, j.title, j.description
        ORDER BY j.id DESC
    ");
    $stmt->execute([$searchLike, $searchLike]);
} else {
    $stmt = $conn->prepare("
        SELECT
            j.id,
            j.image,
            j.title,
            j.description,
            COUNT(DISTINCT l.id) AS lesson_count,
            COUNT(DISTINCT uj_all.user_id) AS enrolled_users
        FROM journeys j
        LEFT JOIN lessons l ON l.journey_id = j.id
        LEFT JOIN user_journeys uj_all ON uj_all.journey_id = j.id
        WHERE (j.title LIKE ? OR j.description LIKE ?)
          AND NOT EXISTS (
              SELECT 1
              FROM user_journeys uj_me
              WHERE uj_me.journey_id = j.id
                AND uj_me.user_id = ?
          )
        GROUP BY j.id, j.image, j.title, j.description
        ORDER BY j.id DESC
    ");
    $stmt->execute([$searchLike, $searchLike, $userId]);
}
$discoverJourneys = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT
        j.id,
        j.image,
        j.title,
        j.description,
        uj.status,
        uj.progress_percent,
        COUNT(DISTINCT l.id) AS lesson_count,
        COUNT(DISTINCT CASE WHEN ulp.is_completed = 1 THEN l.id END) AS completed_lessons
    FROM user_journeys uj
    INNER JOIN journeys j ON j.id = uj.journey_id
    LEFT JOIN lessons l ON l.journey_id = j.id
    LEFT JOIN user_lesson_progress ulp ON ulp.user_id = uj.user_id AND ulp.lesson_id = l.id
    WHERE uj.user_id = ?
      AND uj.status <> 'unenrolled'
      AND (j.title LIKE ? OR j.description LIKE ?)
    GROUP BY j.id, j.image, j.title, j.description, uj.status, uj.progress_percent, uj.id, uj.enrolled_at
    ORDER BY uj.enrolled_at DESC
");
$stmt->execute([$userId, $searchLike, $searchLike]);
$myJourneys = $stmt->fetchAll();
?>

<style>
.search-container {
    position: relative;
}

.search-input {
    height: 45px;
    padding-left: 40px;
    border: 0;
}

.search-icon {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    color: #888;
    left: 10px;
}

.active-tab {
    border-bottom: 2px solid #0d6efd;
}

.journey-section {
    display: none;
}

.journey-section.active {
    display: block;
}

.description-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}
.description-clamp-my {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<header>
    <nav class="sticky-top bg-white pt-3">
        <div class="container">
            <div class="align-items-center d-flex justify-content-between mb-3">
                <a href="home.php">
                    <i class="fa-solid fa-chevron-left text-black hover-btn"></i>
                </a>
                <h5 class="mb-0 text-primary">Journeys</h5>
                <div></div>
            </div>

            <form class="search-container m-0" role="search" method="GET" action="">
                <input
                    type="text"
                    name="q"
                    class="form-control search-input border-bottom"
                    placeholder="Search..."
                    value="<?= htmlspecialchars($search) ?>"
                >
                <i class="fas fa-search search-icon"></i>
            </form>

            <ul class="navbar-nav d-flex flex-row justify-content-between">
                <li class="nav-item w-100 text-center">
                    <a class="nav-link py-3 journey-tab active-tab" href="#" data-target="discoverSection">Discover</a>
                </li>
                <li class="nav-item w-100 text-center">
                    <a class="nav-link py-3 journey-tab" href="#" data-target="myJourneysSection">My Journeys</a>
                </li>
            </ul>
        </div>
    </nav>
</header>

<body class="bg-body-tertiary">
    <div class="container mt-4 journey-section active" id="discoverSection">
        <?php if ($isAdmin): ?>
            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJourneyModal">
                    <i class="fa-solid fa-plus"></i> Add Journey
                </button>
            </div>
        <?php endif; ?>

        <?php if (empty($discoverJourneys)): ?>
            <div class="alert alert-light border">No journeys found.</div>
        <?php endif; ?>

        <?php foreach ($discoverJourneys as $journey): ?>
            <div
                class="card mb-3 journey-card"
                data-bs-toggle="modal"
                data-bs-target="#discoverJourneyModal"
                style="cursor: pointer;"
                data-journey-id="<?= (int) $journey['id'] ?>"
                data-title="<?= htmlspecialchars($journey['title'], ENT_QUOTES) ?>"
                data-description="<?= htmlspecialchars($journey['description'], ENT_QUOTES) ?>"
                data-image="<?= htmlspecialchars($journey['image'], ENT_QUOTES) ?>"
                data-lessons="<?= (int) $journey['lesson_count'] ?>"
                data-users="<?= (int) $journey['enrolled_users'] ?>"
            >
                <div class="d-flex gap-2 w-100 align-items-start hover-card">
                    <div>
                        <img src="<?= htmlspecialchars($journey['image']) ?>" class="rounded-start" alt="Journey image" style="height: 200px; width: 200px; object-fit: cover;">
                    </div>

                    <div class="card-body d-flex justify-content-between align-items-center w-100">
                        <div class="flex-grow-1 pe-3">
                            <h5 class="card-title mb-2"><?= htmlspecialchars($journey['title']) ?></h5>
                            <p class="card-text small mb-2 description-clamp text-break">
                                <?= nl2br(htmlspecialchars($journey['description'])) ?>
                            </p>
                            <div class="d-flex gap-2 flex-wrap">
                                <div class="py-1 px-2 rounded-4 bg-primary-subtle text-primary small">
                                    <i class="fa-solid fa-list-ol"></i>
                                    <span><?= (int) $journey['lesson_count'] ?> lessons</span>
                                </div>
                                <div class="py-1 px-2 rounded-4 bg-primary-subtle text-primary small">
                                    <i class="fa-solid fa-user-group"></i>
                                    <span><?= (int) $journey['enrolled_users'] ?> users</span>
                                </div>
                            </div>
                        </div>

                        <div class="hover-btn rounded-circle bg-primary-subtle d-flex justify-content-center align-items-center flex-shrink-0" style="width: 30px; height: 30px;">
                            <?php if ($isAdmin): ?>
                                <i class="fa-solid fa-gear small text-primary"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-plus small text-primary"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="modal fade" id="discoverJourneyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5">Journey Details</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="d-flex justify-content-center">
                            <img id="discoverModalImage" src="" class="img-fluid rounded" alt="Journey image" style="height: 300px; width: 300px; object-fit: cover;">
                        </div>
                        <div class="mt-3">
                            <h4 class="card-title m-0" id="discoverModalTitle"></h4>

                            <div class="d-flex gap-2 my-3">
                                <div class="py-1 px-2 rounded-4 border border-primary text-primary small">
                                    <i class="fa-solid fa-list-ol"></i>
                                    <span id="discoverModalLessons"></span>
                                </div>
                                <div class="py-1 px-2 rounded-4 border border-primary text-primary small">
                                    <i class="fa-solid fa-user-group"></i>
                                    <span id="discoverModalUsers"></span>
                                </div>
                            </div>

                            <div>
                                <h5>Description</h5>
                                <p id="discoverModalDescription" class="text-break" style="white-space: pre-line;"></p>
                            </div>

                            <input type="hidden" id="currentJourneyId">
                            <input type="hidden" id="currentJourneyTitle">
                            <input type="hidden" id="currentJourneyDescription">
                            <input type="hidden" id="currentJourneyImage">
                        </div>
                    </div>

                    <div class="modal-footer d-flex flex-wrap gap-2">
                        <form method="POST" action="enroll_journey.php" class="flex-fill m-0">
                            <input type="hidden" name="journey_id" id="discoverJourneyId">
                            <button type="submit" class="btn btn-primary w-100">Start Journey</button>
                        </form>

                        <?php if ($isAdmin): ?>
                            <button type="button" class="btn btn-warning flex-fill" id="openUpdateJourneyBtn">
                                Update
                            </button>
                            <button type="button" class="btn btn-danger flex-fill" id="openDeleteJourneyBtn">
                                Delete
                            </button>
                            <a href="#" class="btn btn-secondary flex-fill" id="manageLessonsBtn">
                                Manage Lessons
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <div class="modal fade" id="addJourneyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Journey</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="create_journey">

                            <div class="mb-3">
                                <label>Title</label>
                                <input type="text" name="title" class="form-control" required placeholder="What's the journey title?">
                            </div>

                            <div class="mb-3">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="4" required placeholder="Describe the journey"></textarea>
                            </div>

                            <div class="mb-3">
                                <label>Image</label>
                                <input type="file" name="image" class="form-control" accept="image/*" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save Journey</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="updateJourneyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="modal-header">
                            <h5 class="modal-title">Update Journey</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="update_journey">
                            <input type="hidden" name="journey_id" id="updateJourneyId">

                            <div class="mb-3">
                                <label>Title</label>
                                <input type="text" name="title" id="updateJourneyTitle" class="form-control" required placeholder="What's the journey title?">
                            </div>

                            <div class="mb-3">
                                <label>Description</label>
                                <textarea name="description" id="updateJourneyDescription" class="form-control" rows="4" required placeholder="Describe the journey"></textarea>
                            </div>

                            <div class="mb-3">
                                <label>Replace Image</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-warning">Update Journey</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteJourneyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="">
                        <div class="modal-header">
                            <h5 class="modal-title">Delete Journey</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="delete_journey">
                            <input type="hidden" name="journey_id" id="deleteJourneyId">
                            <p>Are you sure you want to delete <strong id="deleteJourneyTitle"></strong>?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-danger">Yes, Delete</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>




    <div class="container mt-4 journey-section" id="myJourneysSection">
        <?php if (empty($myJourneys)): ?>
            <div class="alert alert-light border">You have no journeys yet.</div>
        <?php endif; ?>

        <?php foreach ($myJourneys as $journey): ?>
            <?php $progress = (int) $journey['progress_percent']; ?>
            <div
                class="card mb-3 journey-card-my"
                data-bs-toggle="modal"
                data-bs-target="#myJourneyModal"
                style="cursor: pointer;"
                data-journey-id="<?= (int) $journey['id'] ?>"
                data-title="<?= htmlspecialchars($journey['title'], ENT_QUOTES) ?>"
                data-description="<?= htmlspecialchars($journey['description'], ENT_QUOTES) ?>"
                data-image="<?= htmlspecialchars($journey['image'], ENT_QUOTES) ?>"
                data-lessons="<?= (int) $journey['lesson_count'] ?>"
                data-progress="<?= $progress ?>"
            >
                <div class="d-flex gap-2 w-100 align-items-start hover-card">
                    <div>
                        <img src="<?= htmlspecialchars($journey['image']) ?>" class="rounded-start" alt="Journey image" style="height: 200px; width: 200px; object-fit: cover;">
                    </div>
                    <div class="card-body d-flex justify-content-between align-items-center w-100">
                        <div class="flex-grow-1 pe-3">
                            <h5 class="card-title mb-2"><?= htmlspecialchars($journey['title']) ?></h5>
                            <p class="card-text small text-muted mb-2 description-clamp-my text-break">
                                <?= nl2br(htmlspecialchars($journey['description'])) ?>
                            </p>
                            <div class="d-flex gap-2 flex-wrap">
                                <div class="py-1 px-2 rounded-4 bg-primary-subtle text-primary small">
                                    <i class="fa-solid fa-list-ol"></i>
                                    <span><?= (int) $journey['lesson_count'] ?> lessons</span>
                                </div>
                                <div class="py-1 px-2 rounded-4 bg-primary-subtle text-primary small">
                                    <i class="fa-solid fa-user"></i>
                                    <span><?= $progress ?>%</span>
                                </div>
                            </div>

                            <div class="mt-3">
                                <span class="text-primary small"><?= $progress ?>% complete</span>
                                <div class="progress mt-2" style="height: 5px">
                                    <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="hover-btn rounded-circle bg-primary-subtle d-flex justify-content-center align-items-center flex-shrink-0" style="width: 30px; height: 30px;">
                            <i class="fa-solid fa-chevron-right small text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="modal fade" id="myJourneyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5">Journey Details</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="d-flex justify-content-center">
                            <img id="myModalImage" src="" class="img-fluid rounded" alt="Journey image" style="height: 300px; width: 300px; object-fit: cover;">
                        </div>
                        <div class="mt-3">
                            <h4 class="card-title m-0" id="myModalTitle"></h4>
                            
                            <div class="d-flex gap-2 my-3">
                                <div class="py-1 px-2 rounded-4 border border-primary text-primary small">
                                    <i class="fa-solid fa-list-ol"></i>
                                    <span id="myModalLessons"></span>
                                </div>
                                <div class="py-1 px-2 rounded-4 border border-primary text-primary small">
                                    <i class="fa-solid fa-user"></i>
                                    <span id="myModalProgress"></span>
                                </div>
                            </div>

                            <div class="border rounded-3 border-primary p-3 my-3 bg-primary-subtle">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="m-0">Your Progress</h5>
                                    <h5 class="m-0 text-primary" id="myModalProgressText"></h5>
                                </div>
                                <div class="progress mt-3 mb-2" style="height: 10px">
                                    <div class="progress-bar" id="myModalProgressBar" style="width: 0%"></div>
                                </div>
                                <span class="text-muted small">Step 8 of 10</span>
                            </div>

                            <div>
                                <h5>Description</h5>
                                <p id="myModalDescription" class="text-break" style="white-space: pre-line;"></p>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer d-flex gap-2">
                        <a href="#" class="btn btn-primary flex-fill" id="continueJourneyBtn">Continue Journey</a>
                        <button type="button" class="btn btn-danger flex-fill" data-bs-toggle="modal" data-bs-target="#confirmUnenrollModal">
                            Unenroll
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmUnenrollModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Confirm Unenroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to unenroll from this journey? This will remove it from My Journeys and show it again in Discover.
                </div>
                <div class="modal-footer">
                    <form method="POST" action="unenroll_journey.php" class="m-0">
                        <input type="hidden" name="journey_id" id="unenrollJourneyId">
                        <button type="submit" class="btn btn-danger">Yes, Unenroll</button>
                    </form>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <br><br>

<script>
document.querySelectorAll('.journey-tab').forEach(tab => {
    tab.addEventListener('click', function (e) {
        e.preventDefault();

        document.querySelectorAll('.journey-tab').forEach(t => t.classList.remove('active-tab'));
        this.classList.add('active-tab');

        const target = this.dataset.target;
        document.querySelectorAll('.journey-section').forEach(section => {
            section.classList.remove('active');
        });

        document.getElementById(target).classList.add('active');
    });
});

document.querySelectorAll('.journey-card').forEach(card => {
    card.addEventListener('click', function () {
        document.getElementById('discoverModalImage').src = this.dataset.image;
        document.getElementById('discoverModalTitle').textContent = this.dataset.title;
        document.getElementById('discoverModalLessons').textContent = this.dataset.lessons + ' lessons';
        document.getElementById('discoverModalUsers').textContent = this.dataset.users + ' users';
        document.getElementById('discoverModalDescription').textContent = this.dataset.description;
        document.getElementById('discoverJourneyId').value = this.dataset.journeyId;

        document.getElementById('currentJourneyId').value = this.dataset.journeyId;
        document.getElementById('currentJourneyTitle').value = this.dataset.title;
        document.getElementById('currentJourneyDescription').value = this.dataset.description;
        document.getElementById('currentJourneyImage').value = this.dataset.image;

        <?php if ($isAdmin): ?>
        document.getElementById('updateJourneyId').value = this.dataset.journeyId;
        document.getElementById('updateJourneyTitle').value = this.dataset.title;
        document.getElementById('updateJourneyDescription').value = this.dataset.description;

        document.getElementById('deleteJourneyId').value = this.dataset.journeyId;
        document.getElementById('deleteJourneyTitle').textContent = this.dataset.title;

        document.getElementById('manageLessonsBtn').href = 'manage_lessons.php?journey_id=' + this.dataset.journeyId;
        <?php endif; ?>
    });
});

<?php if ($isAdmin): ?>
document.getElementById('openUpdateJourneyBtn').addEventListener('click', function () {
    document.getElementById('updateJourneyId').value = document.getElementById('currentJourneyId').value;
    document.getElementById('updateJourneyTitle').value = document.getElementById('currentJourneyTitle').value;
    document.getElementById('updateJourneyDescription').value = document.getElementById('currentJourneyDescription').value;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('discoverJourneyModal')).hide();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('updateJourneyModal')).show();
});

document.getElementById('openDeleteJourneyBtn').addEventListener('click', function () {
    document.getElementById('deleteJourneyId').value = document.getElementById('currentJourneyId').value;
    document.getElementById('deleteJourneyTitle').textContent = document.getElementById('currentJourneyTitle').value;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('discoverJourneyModal')).hide();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteJourneyModal')).show();
});

document.getElementById('manageLessonsBtn').addEventListener('click', function (e) {
    e.preventDefault();
    const journeyId = document.getElementById('currentJourneyId').value;
    window.location.href = 'manage_lessons.php?journey_id=' + journeyId;
});
<?php endif; ?>

document.getElementById('myJourneyModal').addEventListener('show.bs.modal', function (event) {
    const card = event.relatedTarget;

    document.getElementById('myModalImage').src = card.dataset.image;
    document.getElementById('myModalTitle').textContent = card.dataset.title;
    document.getElementById('myModalLessons').textContent = card.dataset.lessons + ' lessons';
    document.getElementById('myModalProgress').textContent = card.dataset.progress + '%';
    document.getElementById('myModalProgressText').textContent = card.dataset.progress + '%';
    document.getElementById('myModalProgressBar').style.width = card.dataset.progress + '%';
    document.getElementById('myModalDescription').textContent = card.dataset.description;

    const journeyId = card.dataset.journeyId;
    document.getElementById('continueJourneyBtn').href = 'content.php?journey_id=' + journeyId;
    document.getElementById('unenrollJourneyId').value = journeyId;
});
</script>
</body>