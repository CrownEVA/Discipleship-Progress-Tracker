<?php
session_start();
date_default_timezone_set('Asia/Manila');

include 'links.php';
include 'database.php';
include 'mailer.php';

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_role"] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

$adminId = (int) $_SESSION["user_id"];
$adminName = $_SESSION["user_name"] ?? 'Administrator';

$search = trim($_GET['q'] ?? '');
$rankFilter = trim($_GET['rank'] ?? '');
$section = $_GET['section'] ?? 'dashboard';

if (!in_array($section, ['dashboard', 'accounts', 'reports'], true)) {
    $section = 'dashboard';
}

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

function sendEmailChangeLink(PDO $conn, int $userId, string $newEmail): bool
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt = $conn->prepare("
        INSERT INTO email_change_requests (user_id, new_email, token_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $newEmail, $tokenHash, $expiresAt]);

    $verifyLink = "http://localhost/Discipleship-Progress-Tracker/verify_email_change.php?token=" . urlencode($token);

    $subject = "Verify your new email";
    $html = "
        <p>Hello,</p>
        <p>Please verify your new email address by clicking the link below.</p>
        <p><a href='{$verifyLink}'>Verify Email Change</a></p>
        <p>This link expires in 24 hours.</p>
    ";

    return sendMail($newEmail, $subject, $html);
}

function cleanAdminMessage(string $message, string $fullName): string
{
    $message = str_ireplace('Start your journey now!', '', $message);
    $message = preg_replace('/\s+/', ' ', trim($message));

    $replacements = [
        "/^You've enrolled in /i" => $fullName . ' enrolled in ',
        "/^You earned /i" => $fullName . ' earned ',
        "/^You lost /i" => $fullName . ' lost ',
        "/^You completed /i" => $fullName . ' completed ',
        "/^You have /i" => $fullName . ' has ',
        "/^You /i" => $fullName . ' ',
    ];

    foreach ($replacements as $pattern => $replace) {
        if (preg_match($pattern, $message)) {
            $message = preg_replace($pattern, $replace, $message, 1);
            break;
        }
    }

    return preg_replace('/\s+/', ' ', trim($message));
}

function fetchRankedAccounts(PDO $conn, string $search = '', string $rankFilter = '', ?int $limit = null): array
{
    $sql = "
        WITH account_base AS (
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
        )
        SELECT *
        FROM (
            SELECT
                ab.*,
                (
                    SELECT COUNT(*) + 1
                    FROM account_base x
                    WHERE x.points > ab.points
                       OR (x.points = ab.points AND x.id < ab.id)
                ) AS rank_no,
                CASE
                    WHEN ab.points >= 4000 THEN 'Spiritual Mentor'
                    WHEN ab.points >= 2000 THEN 'Disciple Maker'
                    WHEN ab.points >= 1000 THEN 'Kingdom Worker'
                    WHEN ab.points >= 500 THEN 'Servant Leader'
                    WHEN ab.points >= 250 THEN 'Faith Builder'
                    WHEN ab.points >= 100 THEN 'Growing Disciple'
                    ELSE 'New Believer'
                END AS level_name
            FROM account_base ab
        ) ranked_accounts
        WHERE 1 = 1
    ";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (CONCAT(first_name, ' ', last_name) LIKE ? OR email LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($rankFilter !== '') {
        $sql .= " AND level_name = ?";
        $params[] = $rankFilter;
    }

    $sql .= " ORDER BY rank_no ASC, points DESC, id ASC";

    if ($limit !== null) {
        $sql .= " LIMIT " . (int) $limit;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function formatNotifDate(string $datetime): string
{
    return date('F j, Y h:i A', strtotime($datetime));
}

$uploadDir = 'uploads/journeys/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];

    try {
        if ($action === "update_user") {
            $targetUserId = (int) ($_POST["user_id"] ?? 0);
            $firstName = trim($_POST["first_name"] ?? '');
            $lastName = trim($_POST["last_name"] ?? '');
            $email = trim($_POST["email"] ?? '');
            $role = trim($_POST["role"] ?? 'user');
            $points = (int) ($_POST["points"] ?? 0);

            $returnSearch = trim($_POST["return_q"] ?? '');
            $returnRank = trim($_POST["return_rank"] ?? '');

            if ($targetUserId <= 0 || $firstName === '' || $lastName === '' || $email === '') {
                $_SESSION["error"] = "Please fill in all required fields.";
                header("Location: admin.php?section=accounts&q=" . urlencode($returnSearch) . "&rank=" . urlencode($returnRank));
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION["error"] = "Invalid email format.";
                header("Location: admin.php?section=accounts&q=" . urlencode($returnSearch) . "&rank=" . urlencode($returnRank));
                exit;
            }

            if (!in_array($role, ['user', 'admin'], true)) {
                $role = 'user';
            }

            $stmt = $conn->prepare("SELECT id, email FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $oldUser = $stmt->fetch();

            if (!$oldUser) {
                $_SESSION["error"] = "User not found.";
                header("Location: admin.php?section=accounts&q=" . urlencode($returnSearch) . "&rank=" . urlencode($returnRank));
                exit;
            }

            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $stmt->execute([$email, $targetUserId]);
            if ($stmt->fetch()) {
                $_SESSION["error"] = "Email is already in use.";
                header("Location: admin.php?section=accounts&q=" . urlencode($returnSearch) . "&rank=" . urlencode($returnRank));
                exit;
            }

            if ($targetUserId === $adminId) {
                $role = 'admin';
            }

            $stmt = $conn->prepare("
                UPDATE users
                SET first_name = ?, last_name = ?, role = ?, points = ?
                WHERE id = ?
            ");
            $stmt->execute([$firstName, $lastName, $role, $points, $targetUserId]);

            if ($email !== $oldUser['email']) {
                if (sendEmailChangeLink($conn, $targetUserId, $email)) {
                    $_SESSION["success"] = "Account updated. A verification link was sent to the new email address.";
                } else {
                    $_SESSION["success"] = "Account updated, but the email verification could not be sent.";
                }
            } else {
                $_SESSION["success"] = "Account updated successfully.";
            }

            header("Location: admin.php?section=accounts&q=" . urlencode($returnSearch) . "&rank=" . urlencode($returnRank));
            exit;
        }

        if ($action === "delete_user") {
            $targetUserId = (int) ($_POST["user_id"] ?? 0);
            $returnSearch = trim($_POST["return_q"] ?? '');
            $returnRank = trim($_POST["return_rank"] ?? '');

            if ($targetUserId <= 0) {
                $_SESSION["error"] = "Invalid user.";
                header("Location: admin.php?section=accounts&q=" . urlencode($returnSearch) . "&rank=" . urlencode($returnRank));
                exit;
            }

            if ($targetUserId === $adminId) {
                $_SESSION["error"] = "You cannot delete your own account while logged in.";
                header("Location: admin.php?section=accounts&q=" . urlencode($returnSearch) . "&rank=" . urlencode($returnRank));
                exit;
            }

            $conn->beginTransaction();

            $stmt = $conn->prepare("DELETE FROM user_lesson_progress WHERE user_id = ?");
            $stmt->execute([$targetUserId]);

            $stmt = $conn->prepare("DELETE FROM user_journeys WHERE user_id = ?");
            $stmt->execute([$targetUserId]);

            $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt->execute([$targetUserId]);

            $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
            $stmt->execute([$targetUserId]);

            $stmt = $conn->prepare("DELETE FROM email_change_requests WHERE user_id = ?");
            $stmt->execute([$targetUserId]);

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);

            $conn->commit();

            $_SESSION["success"] = "Account deleted successfully.";
            header("Location: admin.php?section=accounts&q=" . urlencode($returnSearch) . "&rank=" . urlencode($returnRank));
            exit;
        }

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

                $_SESSION["success"] = "Journey added successfully.";
            } else {
                $_SESSION["error"] = "Please fill in all fields and upload a valid image.";
            }

            header("Location: admin.php?section=dashboard");
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

                $_SESSION["success"] = "Journey updated successfully.";
            } else {
                $_SESSION["error"] = "Unable to update journey.";
            }

            header("Location: admin.php?section=dashboard");
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
                $_SESSION["success"] = "Journey deleted successfully.";
            }

            header("Location: admin.php?section=dashboard");
            exit;
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION["error"] = "Action failed: " . $e->getMessage();
        header("Location: admin.php?section=" . urlencode($section));
        exit;
    }
}

$totalUsers = (int) $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalJourneys = (int) $conn->query("SELECT COUNT(*) FROM journeys")->fetchColumn();
$totalLessons = (int) $conn->query("SELECT COUNT(*) FROM lessons")->fetchColumn();

$allAccounts = fetchRankedAccounts($conn, '', '', null);
$dashboardAccounts = array_slice($allAccounts, 0, 5);
$filteredAccounts = fetchRankedAccounts($conn, $search, $rankFilter, null);

$stmt = $conn->prepare("
    SELECT
        n.id,
        n.user_id,
        n.title,
        n.message,
        n.type,
        n.is_read,
        n.created_at,
        CONCAT(u.first_name, ' ', u.last_name) AS user_full_name,
        j.title AS journey_title,
        l.title AS lesson_title
    FROM notifications n
    INNER JOIN users u ON u.id = n.user_id
    LEFT JOIN journeys j ON j.id = n.journey_id
    LEFT JOIN lessons l ON l.id = n.lesson_id
    ORDER BY n.created_at DESC, n.id DESC
");
$stmt->execute();
$notificationRows = $stmt->fetchAll();

$notificationLogs = [];
foreach ($notificationRows as $row) {
    $uid = (int) $row['user_id'];
    $fullName = $row['user_full_name'];

    if (!isset($notificationLogs[$uid])) {
        $notificationLogs[$uid] = [
            'name' => $fullName,
            'items' => []
        ];
    }

    $notificationLogs[$uid]['items'][] = [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'message' => cleanAdminMessage($row['message'], $fullName),
        'type' => $row['type'],
        'is_read' => (int) $row['is_read'],
        'created_at' => formatNotifDate($row['created_at']),
        'journey_title' => $row['journey_title'] ?? '',
        'lesson_title' => $row['lesson_title'] ?? '',
    ];
}

$stmt = $conn->query("
    SELECT j.id, j.title, COUNT(uj.id) AS enrollments
    FROM journeys j
    LEFT JOIN user_journeys uj ON uj.journey_id = j.id AND uj.status <> 'unenrolled'
    GROUP BY j.id, j.title
    ORDER BY enrollments DESC, j.id DESC
    LIMIT 5
");
$topJourneyRows = $stmt->fetchAll();

$topJourneyLabels = [];
$topJourneyValues = [];
foreach ($topJourneyRows as $row) {
    $topJourneyLabels[] = $row['title'];
    $topJourneyValues[] = (int) $row['enrollments'];
}

$stmt = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM user_journeys
    GROUP BY status
");
$statusRows = $stmt->fetchAll();
$statusLabels = [];
$statusValues = [];
foreach ($statusRows as $row) {
    $statusLabels[] = ucfirst($row['status']);
    $statusValues[] = (int) $row['total'];
}

$levelOrder = [
    'New Believer',
    'Growing Disciple',
    'Faith Builder',
    'Servant Leader',
    'Kingdom Worker',
    'Disciple Maker',
    'Spiritual Mentor'
];

$levelCounts = array_fill_keys($levelOrder, 0);
foreach ($allAccounts as $account) {
    $levelCounts[$account['level_name']] = ($levelCounts[$account['level_name']] ?? 0) + 1;
}

$monthlyCounts = array_fill_keys(
    ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    0
);

$stmt = $conn->query("
    SELECT DATE_FORMAT(completed_at, '%b') AS month_name, COUNT(*) AS total
    FROM user_lesson_progress
    WHERE is_completed = 1
      AND completed_at IS NOT NULL
      AND YEAR(completed_at) = YEAR(CURDATE())
    GROUP BY DATE_FORMAT(completed_at, '%b'), MONTH(completed_at)
    ORDER BY MONTH(completed_at)
");
foreach ($stmt->fetchAll() as $row) {
    $monthlyCounts[$row['month_name']] = (int) $row['total'];
}

$activeSection = in_array($section, ['dashboard', 'accounts', 'reports'], true) ? $section : 'dashboard';

$dashboardTopJourneyLabels = json_encode($topJourneyLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$dashboardTopJourneyValues = json_encode($topJourneyValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$dashboardLevelLabels = json_encode(array_keys($levelCounts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$dashboardLevelValues = json_encode(array_values($levelCounts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$reportStatusLabels = json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$reportStatusValues = json_encode($statusValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$reportMonthlyLabels = json_encode(array_keys($monthlyCounts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$reportMonthlyValues = json_encode(array_values($monthlyCounts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$reportTopJourneyLabels = $dashboardTopJourneyLabels;
$reportTopJourneyValues = $dashboardTopJourneyValues;
$reportLevelLabels = $dashboardLevelLabels;
$reportLevelValues = $dashboardLevelValues;
?>

<style>
.sidebar-panel {
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    background-image: url('assets/bg.jpg');
    background-size: cover;
    align-self: flex-start;
}

.sidebar-overlay {
    min-height: 100vh;
}

.sidebar-nav .btn {
    text-align: left;
    color: #fff;
    border: 0;
    background: rgba(255, 255, 255, 0.10);
}

.sidebar-nav .btn.active {
    background: #fff;
    color: #0d6efd;
}

.app-section {
    display: none;
}

.app-section.active {
    display: block;
}

.hover-card {
    transition: transform 0.2s ease;
}

.hover-card:hover {
    transform: translateY(-2px);
}

.small-chart {
    height: 270px;
}

.large-chart {
    height: 320px;
}

.log-list {
    max-height: 60vh;
    overflow: auto;
}
#reportArea {
    transform: scale(0.92);
    transform-origin: top left;
    width: 108.7%;
}
</style>

<body class="bg-body-tertiary">
    <div class="container-fluid g-0">
        <div class="row g-0 align-items-start">
            <aside class="col-12 col-lg-3 col-xl-2 sidebar-panel">
                <div class="sidebar-overlay p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="rounded-circle bg-white text-primary d-flex justify-content-center align-items-center" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-user-shield h5 m-0"></i>
                        </div>
                        <div>
                            <h6 class="text-white mb-0"><?= htmlspecialchars($adminName) ?></h6>
                            <span class="text-white-50 small">Administrator</span>
                        </div>
                    </div>

                    <div class="sidebar-nav d-grid gap-2">
                        <button type="button" class="btn px-3 py-3 rounded-3 <?= $activeSection === 'dashboard' ? 'active' : '' ?>" data-section="dashboard">
                            <i class="fa-solid fa-table-cells me-2"></i> Dashboard
                        </button>
                        <button type="button" class="btn px-3 py-3 rounded-3 <?= $activeSection === 'accounts' ? 'active' : '' ?>" data-section="accounts">
                            <i class="fa-solid fa-users me-2"></i> Accounts
                        </button>
                        <button type="button" class="btn px-3 py-3 rounded-3 <?= $activeSection === 'reports' ? 'active' : '' ?>" data-section="reports">
                            <i class="fa-solid fa-chart-column me-2"></i> Reports
                        </button>
                    </div>

                    <div class="mt-auto pt-4">
                        <a href="home.php" class="btn btn-outline-light w-100 mb-2">
                            <i class="fa-solid fa-house me-2"></i> Back to Home
                        </a>
                        <a href="logout.php" class="btn btn-outline-light w-100">
                            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </aside>

            <main class="col-12 col-lg-9 col-xl-10 p-4">
                <?php if (isset($_SESSION["success"])): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($_SESSION["success"]) ?>
                    </div>
                    <?php unset($_SESSION["success"]); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION["error"])): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($_SESSION["error"]) ?>
                    </div>
                    <?php unset($_SESSION["error"]); ?>
                <?php endif; ?>

                <section id="dashboardSection" class="app-section <?= $activeSection === 'dashboard' ? 'active' : '' ?>">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h3 class="mb-1">Dashboard</h3>
                            <span class="text-muted">Overview of the whole system</span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded-3 border-primary p-4 bg-white hover-card text-center">
                                <i class="fa-solid fa-users mb-2 text-primary h5"></i>
                                <h2 class="mb-1"><?= (int) $totalUsers ?></h2>
                                <span class="text-muted">Number of disciples</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-3 border-primary p-4 bg-white hover-card text-center">
                                <i class="fa-solid fa-flag mb-2 text-primary h5"></i>
                                <h2 class="mb-1"><?= (int) $totalJourneys ?></h2>
                                <span class="text-muted">Number of journeys</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-3 border-primary p-4 bg-white hover-card text-center">
                                <i class="fa-solid fa-book-open mb-2 text-primary h5"></i>
                                <h2 class="mb-1"><?= (int) $totalLessons ?></h2>
                                <span class="text-muted">Number of lessons</span>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded-3 border-primary p-4 bg-white mt-4">
                        <div class="d-flex justify-content-between align-items-center border-bottom border-muted pb-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-users text-primary h5 p-0 m-0"></i>
                                <h5 class="m-0">Accounts Preview</h5>
                            </div>
                            <button type="button" class="btn text-primary" onclick="showSection('accounts')">View all</button>
                        </div>

                        <ul class="list-group list-group-flush">
                            <?php if (empty($dashboardAccounts)): ?>
                                <li class="list-group-item px-0 py-3">No accounts available.</li>
                            <?php else: ?>
                                <?php foreach ($dashboardAccounts as $account): ?>
                                    <li class="list-group-item px-0 py-3">
                                        <div class="d-flex justify-content-between align-items-center gap-3">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?></h6>
                                                <span class="text-muted small"><?= htmlspecialchars($account['email']) ?></span>
                                            </div>
                                            <div class="text-end">
                                                <div class="small text-primary"><?= htmlspecialchars($account['level_name']) ?></div>
                                                <div class="small text-muted"><?= (int) $account['points'] ?> pts</div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="border rounded-3 border-primary p-4 bg-white mt-4">
                        <div class="d-flex justify-content-between align-items-center border-bottom border-muted pb-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-chart-simple text-primary h5 p-0 m-0"></i>
                                <h5 class="m-0">Reports Preview</h5>
                            </div>
                            <button type="button" class="btn text-primary" onclick="showSection('reports')">View all</button>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="border rounded-3 p-4">
                                    <h6 class="mb-3">Most Enrolled Journeys</h6>
                                    <div class="small-chart">
                                        <canvas id="dashTopJourneyChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-4">
                                    <h6 class="mb-3">User Levels</h6>
                                    <div class="small-chart">
                                        <canvas id="dashLevelChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="accountsSection" class="app-section <?= $activeSection === 'accounts' ? 'active' : '' ?>">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h3 class="mb-1">Accounts</h3>
                            <span class="text-muted">Search, filter, update, delete, and view logs</span>
                        </div>
                    </div>

                    <div class="border rounded-3 border-primary p-4 bg-white mb-4">
                        <form method="GET" action="" class="row g-3 align-items-end">
                            <input type="hidden" name="section" value="accounts">

                            <div class="col-md-5">
                                <label class="form-label">Search</label>
                                <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or email">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Rank Filter</label>
                                <select name="rank" class="form-select">
                                    <option value="">All ranks</option>
                                    <?php foreach ($levelOrder as $levelName): ?>
                                        <option value="<?= htmlspecialchars($levelName) ?>" <?= $rankFilter === $levelName ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($levelName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                                <a href="admin.php?section=accounts" class="btn btn-outline-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="border rounded-3 border-primary p-4 bg-white">
                        <div class="d-flex justify-content-between align-items-center border-bottom border-muted pb-3 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-users text-primary h5 p-0 m-0"></i>
                                <h5 class="m-0">Account List</h5>
                            </div>
                            <span class="text-muted small"><?= count($filteredAccounts) ?> result(s)</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr class="text-center">
                                        <th>Rank</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Points</th>
                                        <th>Level</th>
                                        <th>Active</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($filteredAccounts)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">No accounts found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($filteredAccounts as $account): ?>
                                            <tr class="text-center">
                                                <td><?= (int) $account['rank_no'] ?></td>
                                                <td><?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?></td>
                                                <td><?= htmlspecialchars($account['email']) ?></td>
                                                <td>
                                                    <span class="border small px-2 py-1 rounded text-muted"><?= htmlspecialchars($account['role']) ?></span>
                                                </td>
                                                <td><?= (int) $account['points'] ?></td>
                                                <td><?= htmlspecialchars($account['level_name']) ?></td>
                                                <td><?= (int) $account['active_areas'] ?></td>
                                                <td>
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <button
                                                            class="btn btn-outline-info p-2"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#logUserModal"
                                                            data-user-id="<?= (int) $account['id'] ?>"
                                                            data-user-name="<?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name'], ENT_QUOTES) ?>"
                                                        >
                                                            <i class="fa-solid fa-clipboard-list"></i>
                                                        </button>

                                                        <button
                                                            class="btn btn-outline-warning p-2"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#updateUserModal"
                                                            data-user-id="<?= (int) $account['id'] ?>"
                                                            data-first-name="<?= htmlspecialchars($account['first_name'], ENT_QUOTES) ?>"
                                                            data-last-name="<?= htmlspecialchars($account['last_name'], ENT_QUOTES) ?>"
                                                            data-email="<?= htmlspecialchars($account['email'], ENT_QUOTES) ?>"
                                                            data-role="<?= htmlspecialchars($account['role'], ENT_QUOTES) ?>"
                                                            data-points="<?= (int) $account['points'] ?>"
                                                        >
                                                            <i class="fa-solid fa-pen"></i>
                                                        </button>

                                                        <?php if ((int) $account['id'] !== $adminId): ?>
                                                            <button
                                                                class="btn btn-outline-danger p-2"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#deleteUserModal"
                                                                data-user-id="<?= (int) $account['id'] ?>"
                                                                data-user-name="<?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name'], ENT_QUOTES) ?>"
                                                            >
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-danger p-2" disabled>
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="modal fade" id="updateUserModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update Account</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>

                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="update_user">
                                        <input type="hidden" name="user_id" id="updateUserId">
                                        <input type="hidden" name="return_q" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
                                        <input type="hidden" name="return_rank" value="<?= htmlspecialchars($rankFilter, ENT_QUOTES) ?>">

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
                                            <select name="role" id="updateRole" class="form-select">
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
                                <form method="POST" action="">
                                    <div class="modal-header">
                                        <h5 class="modal-title text-danger">Delete Account</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>

                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" id="deleteUserId">
                                        <input type="hidden" name="return_q" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
                                        <input type="hidden" name="return_rank" value="<?= htmlspecialchars($rankFilter, ENT_QUOTES) ?>">
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

                    <div class="modal fade" id="logUserModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="logUserName"></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="logUserItems" class="log-list"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="reportsSection" class="app-section <?= $activeSection === 'reports' ? 'active' : '' ?>">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h3 class="mb-1">Reports</h3>
                            <span class="text-muted">System charts and summary view</span>
                        </div>

                        <button type="button" class="btn btn-secondary" onclick="exportReport()">
                            <i class="fa-solid fa-file-export"></i> Export to PDF
                        </button>
                    </div>

                    <div id="reportArea">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="border rounded-3 border-primary p-4 bg-white hover-card">
                                    <h5 class="mb-3">Most Enrolled Journeys</h5>
                                    <div class="large-chart">
                                        <canvas id="reportTopJourneyChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="border rounded-3 border-primary p-4 bg-white hover-card">
                                    <h5 class="mb-3">User Levels</h5>
                                    <div class="large-chart">
                                        <canvas id="reportLevelChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="border rounded-3 border-primary p-4 bg-white hover-card">
                                    <h5 class="mb-3">Journey Status Distribution</h5>
                                    <div class="large-chart">
                                        <canvas id="reportStatusChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="border rounded-3 border-primary p-4 bg-white hover-card">
                                    <h5 class="mb-3">Lesson Completions This Year</h5>
                                    <div class="large-chart">
                                        <canvas id="reportMonthlyChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    const dashboardTopJourneyLabels = <?= $dashboardTopJourneyLabels ?>;
    const dashboardTopJourneyValues = <?= $dashboardTopJourneyValues ?>;
    const dashboardLevelLabels = <?= $dashboardLevelLabels ?>;
    const dashboardLevelValues = <?= $dashboardLevelValues ?>;

    const reportTopJourneyLabels = <?= $reportTopJourneyLabels ?>;
    const reportTopJourneyValues = <?= $reportTopJourneyValues ?>;
    const reportLevelLabels = <?= $reportLevelLabels ?>;
    const reportLevelValues = <?= $reportLevelValues ?>;
    const reportStatusLabels = <?= $reportStatusLabels ?>;
    const reportStatusValues = <?= $reportStatusValues ?>;
    const reportMonthlyLabels = <?= $reportMonthlyLabels ?>;
    const reportMonthlyValues = <?= $reportMonthlyValues ?>;

    const allSections = document.querySelectorAll('.app-section');
    const sidebarButtons = document.querySelectorAll('.sidebar-nav [data-section]');

    let dashboardChartsReady = false;
    let reportsChartsReady = false;
    let dashTopJourneyChart = null;
    let dashLevelChart = null;
    let reportTopJourneyChart = null;
    let reportLevelChart = null;
    let reportStatusChart = null;
    let reportMonthlyChart = null;

    function showSection(sectionId) {
        allSections.forEach(section => {
            section.classList.remove('active');
        });

        sidebarButtons.forEach(btn => {
            btn.classList.remove('active');
        });

        const targetSection = document.getElementById(sectionId + 'Section');
        if (targetSection) {
            targetSection.classList.add('active');
        }

        const activeBtn = document.querySelector('.sidebar-nav [data-section="' + sectionId + '"]');
        if (activeBtn) {
            activeBtn.classList.add('active');
        }

        if (sectionId === 'dashboard' && !dashboardChartsReady) {
            initDashboardCharts();
            dashboardChartsReady = true;
        }

        if (sectionId === 'reports' && !reportsChartsReady) {
            initReportsCharts();
            reportsChartsReady = true;
        }
    }

    function initDashboardCharts() {
        const topJourneyCtx = document.getElementById('dashTopJourneyChart');
        const levelCtx = document.getElementById('dashLevelChart');

        if (topJourneyCtx) {
            dashTopJourneyChart = new Chart(topJourneyCtx, {
                type: 'bar',
                data: {
                    labels: dashboardTopJourneyLabels.length ? dashboardTopJourneyLabels : ['No data'],
                    datasets: [{
                        label: 'Enrollments',
                        data: dashboardTopJourneyValues.length ? dashboardTopJourneyValues : [0]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        if (levelCtx) {
            dashLevelChart = new Chart(levelCtx, {
                type: 'doughnut',
                data: {
                    labels: dashboardLevelLabels,
                    datasets: [{
                        data: dashboardLevelValues
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    }

    function initReportsCharts() {
        const topJourneyCtx = document.getElementById('reportTopJourneyChart');
        const levelCtx = document.getElementById('reportLevelChart');
        const statusCtx = document.getElementById('reportStatusChart');
        const monthlyCtx = document.getElementById('reportMonthlyChart');

        if (topJourneyCtx) {
            reportTopJourneyChart = new Chart(topJourneyCtx, {
                type: 'bar',
                data: {
                    labels: reportTopJourneyLabels.length ? reportTopJourneyLabels : ['No data'],
                    datasets: [{
                        label: 'Enrollments',
                        data: reportTopJourneyValues.length ? reportTopJourneyValues : [0]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        if (levelCtx) {
            reportLevelChart = new Chart(levelCtx, {
                type: 'doughnut',
                data: {
                    labels: reportLevelLabels,
                    datasets: [{
                        data: reportLevelValues
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        if (statusCtx) {
            reportStatusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: reportStatusLabels.length ? reportStatusLabels : ['No data'],
                    datasets: [{
                        data: reportStatusValues.length ? reportStatusValues : [0]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        if (monthlyCtx) {
            reportMonthlyChart = new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: reportMonthlyLabels,
                    datasets: [{
                        label: 'Completions',
                        data: reportMonthlyValues
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('dashboardSection') && document.getElementById('dashboardSection').classList.contains('active')) {
            initDashboardCharts();
            dashboardChartsReady = true;
        }

        if (document.getElementById('reportsSection') && document.getElementById('reportsSection').classList.contains('active')) {
            initReportsCharts();
            reportsChartsReady = true;
        }

        sidebarButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                showSection(this.dataset.section);
            });
        });

        const updateUserModal = document.getElementById('updateUserModal');
        if (updateUserModal) {
            updateUserModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('updateUserId').value = button.dataset.userId;
                document.getElementById('updateFirstName').value = button.dataset.firstName;
                document.getElementById('updateLastName').value = button.dataset.lastName;
                document.getElementById('updateEmail').value = button.dataset.email;
                document.getElementById('updateRole').value = button.dataset.role;
                document.getElementById('updatePoints').value = button.dataset.points;
            });
        }

        const deleteUserModal = document.getElementById('deleteUserModal');
        if (deleteUserModal) {
            deleteUserModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('deleteUserId').value = button.dataset.userId;
                document.getElementById('deleteUserName').textContent = button.dataset.userName;
            });
        }

        const logUserModal = document.getElementById('logUserModal');
        if (logUserModal) {
            logUserModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const userId = button.dataset.userId;
                const userName = button.dataset.userName;

                document.getElementById('logUserName').textContent = userName;

                const container = document.getElementById('logUserItems');
                container.innerHTML = '';

                const data = window.userLogs && window.userLogs[userId] ? window.userLogs[userId] : null;

                if (!data || !data.items || !data.items.length) {
                    container.innerHTML = '<div class="alert alert-light border mb-0">No notifications found for this user.</div>';
                    return;
                }

                data.items.forEach(item => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'border rounded-3 p-3 mb-3 bg-white';

                    const topRow = document.createElement('div');
                    topRow.className = 'd-flex justify-content-between align-items-start gap-3';

                    const left = document.createElement('div');
                    left.className = 'flex-grow-1';

                    const title = document.createElement('h5');
                    title.className = 'mb-1';
                    title.textContent = item.title;

                    const msg = document.createElement('p');
                    msg.className = 'mb-1';
                    msg.textContent = item.message;

                    const metaParts = [item.created_at];
                    if (item.journey_title) metaParts.push(item.journey_title);
                    if (item.lesson_title) metaParts.push(item.lesson_title);

                    const meta = document.createElement('div');
                    meta.className = 'text-muted small';
                    meta.textContent = metaParts.join(' • ');

                    left.appendChild(title);
                    left.appendChild(msg);
                    left.appendChild(meta);

                    const badge = document.createElement('span');
                    badge.className = 'badge ' + (item.is_read ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary');
                    badge.textContent = item.is_read ? 'Read' : 'Unread';

                    topRow.appendChild(left);
                    topRow.appendChild(badge);
                    wrapper.appendChild(topRow);

                    container.appendChild(wrapper);
                });
            });
        }

        window.userLogs = <?= json_encode($notificationLogs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function exportReport() {
            const element = document.getElementById('reportArea');

            const now = new Date();
            const pad = n => String(n).padStart(2, '0');

            const filename =
                'admin-report-' +
                now.getFullYear() + '-' +
                pad(now.getMonth() + 1) + '-' +
                pad(now.getDate()) + '_' +
                pad(now.getHours()) + '-' +
                pad(now.getMinutes()) + '-' +
                pad(now.getSeconds()) +
                '.pdf';

            const opt = {
                margin: 0,
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true
                },
                jsPDF: {
                    unit: 'in',
                    format: 'a4',
                    orientation: 'landscape'
                }
            };

            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>