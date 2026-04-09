<?php
session_start();
include 'links.php';
include 'database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

function isAdmin(): bool {
    return isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin";
}

date_default_timezone_set('Asia/Manila');

$userId = (int) $_SESSION["user_id"];

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

function getStreak(PDO $conn, int $userId): int
{
    $stmt = $conn->prepare("
        SELECT DISTINCT DATE(completed_at) AS done_date
        FROM user_lesson_progress
        WHERE user_id = ?
          AND is_completed = 1
          AND completed_at IS NOT NULL
        ORDER BY done_date DESC
    ");
    $stmt->execute([$userId]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$dates) {
        return 0;
    }

    $streak = 0;
    $expected = new DateTimeImmutable($dates[0]);

    foreach ($dates as $date) {
        $current = new DateTimeImmutable($date);
        if ($current->format('Y-m-d') === $expected->format('Y-m-d')) {
            $streak++;
            $expected = $expected->modify('-1 day');
        } else {
            break;
        }
    }

    return $streak;
}

$stmt = $conn->prepare("
    SELECT id, first_name, last_name, email, COALESCE(points, 0) AS points
    FROM users
    WHERE id = ?
");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$points = (int) $currentUser['points'];
$levelInfo = getLevelInfo($points);
$streak = getStreak($conn, $userId);

$stmt = $conn->prepare("
    SELECT
        j.id,
        j.image,
        j.title,
        j.description,
        COUNT(DISTINCT l.id) AS lesson_count,
        COUNT(DISTINCT CASE WHEN ulp.is_completed = 1 THEN l.id END) AS completed_lessons,
        COALESCE(uj.progress_percent, 0) AS progress_percent
    FROM user_journeys uj
    INNER JOIN journeys j ON j.id = uj.journey_id
    LEFT JOIN lessons l ON l.journey_id = j.id
    LEFT JOIN user_lesson_progress ulp
        ON ulp.user_id = uj.user_id
       AND ulp.lesson_id = l.id
    WHERE uj.user_id = ?
      AND uj.status <> 'unenrolled'
    GROUP BY j.id, j.image, j.title, j.description, uj.progress_percent, uj.id, uj.enrolled_at
    ORDER BY uj.enrolled_at DESC
");
$stmt->execute([$userId]);
$enrolledJourneys = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.role,
        COALESCE(u.points, 0) AS points,
        COUNT(DISTINCT CASE WHEN uj.status <> 'unenrolled' THEN uj.journey_id END) AS active_areas
    FROM users u
    LEFT JOIN user_journeys uj ON uj.user_id = u.id
    GROUP BY u.id, u.first_name, u.last_name, u.email, u.role, u.points
    ORDER BY u.role DESC, u.points DESC, u.id ASC
");
$stmt->execute();
$accounts = $stmt->fetchAll();
?>

<header>
    <nav class="navbar bg-primary py-3">
        <div class="container">
            <a class="navbar-brand" href="account.php"><i class="fa-solid fa-user text-white h4"></i></a>
            <div class="align-items-center d-flex flex-column">
                <h5 class="m-0 text-white">Welcome, <?= htmlspecialchars($_SESSION["user_name"]) ?>!</h5>
                <span class="text-white">Are you ready to win souls day?</span>
            </div>
            <a href="notifications.php"><i class="fa-solid fa-bell text-white h4"></i></a>
        </div>
    </nav>
</header>

<body class="bg-body-tertiary">
    <a href="reports.php" class="text-decoration-none">
        <div class="container mt-4 border rounded-3 border-primary p-4 d-flex justify-content-around bg-white">
            <div class="d-flex flex-column align-items-center border-end border-primary w-100">
                <i class="fa-solid fa-star mb-1"></i>
                <h4 class="m-0 py-1"><?= (int) $points ?></h4>
                <span class="text-black">Points</span>
            </div>
            <div class="d-flex flex-column align-items-center border-end border-primary w-100">
                <i class="fa-solid fa-fire mb-1"></i>
                <h4 class="m-0 py-1"><?= (int) $streak ?></h4>
                <span class="text-black">Streak</span>
            </div>
            <div class="d-flex flex-column align-items-center w-100">
                <i class="fa-solid fa-trophy"></i>
                <h4 class="m-0 py-1"><?= htmlspecialchars($levelInfo['name']) ?></h4>
                <span class="text-black">Level</span>
            </div>
        </div>
    </a>

    <?php if ($enrolledJourneys): ?>
    <div class="container mt-4 border rounded-3 border-primary p-4 bg-white">
        <div class="container-header d-flex justify-content-between align-items-center border-bottom border-muted pb-3">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-flag text-primary h5 p-0 m-0"></i>
                <h5 class="m-0">Journeys</h5>
            </div>
            <a href="journeys.php" class="btn text-primary">View all</a>
        </div>
        <ul class="list-group list-group-flush">
            <?php foreach ($enrolledJourneys as $journey): ?>
                <?php
                    $lessonCount = (int) $journey['lesson_count'];
                    $completedLessons = (int) $journey['completed_lessons'];
                    $progressPercent = (int) $journey['progress_percent'];

                    if ($lessonCount > 0 && $progressPercent === 0 && $completedLessons > 0) {
                        $progressPercent = (int) floor(($completedLessons / $lessonCount) * 100);
                    }

                    $currentStep = min($completedLessons + 1, max($lessonCount, 1));
                ?>
                <li class="list-group-item py-3 px-0">
                    <div class="d-flex gap-3 align-items-center">
                        <img src="<?= htmlspecialchars($journey['image']) ?>" alt="Journey image" class="rounded-3" style="width: 70px; height: 70px; object-fit: cover;">
                        <div class="w-100">
                            <h6 class="mb-1"><?= htmlspecialchars($journey['title']) ?></h6>
                            <span class="text-muted small">
                                <?= $progressPercent > 0 ? $progressPercent . '% complete' : 'Not started' ?>
                            </span>

                            <div class="progress my-3" style="height: 5px">
                                <div class="progress-bar" style="width: <?= $progressPercent ?>%"></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small">
                                    Step <?= $currentStep ?> of <?= $lessonCount > 0 ? $lessonCount : 1 ?>
                                </span>
                                <a href="content.php?journey_id=<?= (int) $journey['id'] ?>" class="btn btn-primary">
                                    <?= $progressPercent > 0 ? 'Continue' : 'Start' ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (empty($enrolledJourneys)): ?>
    <a href="journeys.php" class="text-decoration-none">
        <div class="container mt-4 border rounded-3 border-primary p-4 bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <i class="fa-solid fa-compass text-primary h5 p-0 m-0"></i>
                <div class="text-center">
                    <h5 class="m-0">Start Your Journey</h5>
                    <span class="text-muted small">Discover spiritual growth paths</span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </div>
        </div>
    </a>
    <?php endif; ?>

    <?php if (isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin"): ?>
    <div class="container my-4 border rounded-3 border-primary p-4 bg-white">
        <div class="d-flex align-items-center gap-2 border-bottom border-muted pb-4">
            <i class="fa-solid fa-users text-primary h5 p-0 m-0"></i>
            <h5 class="m-0">Accounts</h5>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Points</th>
                    <th scope="col">Level</th>
                    <th scope="col">Active Areas</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $index => $account): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?></td>
                    <td><?= htmlspecialchars($account['email']) ?></td>
                    <td><?= (int) $account['points'] ?></td>
                    <td>
                        <?php
                            $level = getLevelInfo((int) $account['points']);
                            echo htmlspecialchars($level['name']);
                        ?>
                    </td>
                    <td><?= (int) $account['active_areas'] ?></td>
                    <td>
                        <button
                            class="btn btn-outline-warning btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#updateUserModal"
                            data-user-id="<?= (int) $account['id'] ?>"
                            data-first-name="<?= htmlspecialchars($account['first_name'], ENT_QUOTES) ?>"
                            data-last-name="<?= htmlspecialchars($account['last_name'], ENT_QUOTES) ?>"
                            data-email="<?= htmlspecialchars($account['email'], ENT_QUOTES) ?>"
                            data-role="<?= htmlspecialchars($account['role'], ENT_QUOTES) ?>"
                            data-points="<?= (int) $account['points'] ?>"
                        >
                            Update
                        </button>

                        <button
                            class="btn btn-outline-danger btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteUserModal"
                            data-user-id="<?= (int) $account['id'] ?>"
                            data-user-name="<?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name'], ENT_QUOTES) ?>"
                        >
                            Delete
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="modal fade" id="updateUserModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="admin_update_user.php">
                        <div class="modal-header">
                            <h5 class="modal-title">Update Account</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <input type="hidden" name="user_id" id="updateUserId">

                            <div class="mb-3">
                                <label>First Name</label>
                                <input type="text" name="first_name" id="updateFirstName" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label>Last Name</label>
                                <input type="text" name="last_name" id="updateLastName" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" id="updateEmail" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label>Role</label>
                                <select name="role" id="updateRole" class="form-control">
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label>Points</label>
                                <input type="number" name="points" id="updatePoints" class="form-control" min="0">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="admin_delete_user.php">
                        <div class="modal-header">
                            <h5 class="modal-title text-danger">Delete Account</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <input type="hidden" name="user_id" id="deleteUserId">
                            <p>Are you sure you want to delete <strong id="deleteUserName"></strong>?</p>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-danger">Yes, Delete</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    document.getElementById('updateUserModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('updateUserId').value = button.dataset.userId;
        document.getElementById('updateFirstName').value = button.dataset.firstName;
        document.getElementById('updateLastName').value = button.dataset.lastName;
        document.getElementById('updateEmail').value = button.dataset.email;
        document.getElementById('updateRole').value = button.dataset.role;
        document.getElementById('updatePoints').value = button.dataset.points;
    });

    document.getElementById('deleteUserModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('deleteUserId').value = button.dataset.userId;
        document.getElementById('deleteUserName').textContent = button.dataset.userName;
    });
    </script>
</body>
