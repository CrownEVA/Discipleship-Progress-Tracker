<?php
session_start();
include 'links.php';
include 'database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
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

$stmt = $conn->prepare("
    SELECT id, first_name, last_name, COALESCE(points, 0) AS points
    FROM users
    ORDER BY points DESC, id ASC
");
$stmt->execute();
$leaderboard = $stmt->fetchAll();

$totalUsers = count($leaderboard);
$currentUserData = null;
$currentUserRank = 0;

foreach ($leaderboard as $index => $row) {
    $rank = $index + 1;
    if ((int) $row['id'] === $userId) {
        $currentUserData = $row;
        $currentUserRank = $rank;
        break;
    }
}

if (!$currentUserData) {
    $stmt = $conn->prepare("
        SELECT id, first_name, last_name, COALESCE(points, 0) AS points
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $currentUserData = $stmt->fetch() ?: ['first_name' => 'User', 'last_name' => '', 'points' => 0];
}

$points = (int) $currentUserData['points'];
$levelInfo = getLevelInfo($points);

$topPercent = $totalUsers > 0 ? round(($currentUserRank / $totalUsers) * 100, 1) : 0;
$aheadPercent = $totalUsers > 0 ? round((($totalUsers - $currentUserRank) / $totalUsers) * 100, 1) : 0;
$activeAreas = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS active_areas
    FROM user_journeys
    WHERE user_id = ?
      AND status <> 'unenrolled'
");
$stmt->execute([$userId]);
$activeAreas = (int) $stmt->fetch()['active_areas'];

$nextLevelPoints = $levelInfo['points_to_next'];
?>

<header>
    <nav class="sticky-top bg-white py-3">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="home.php">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <h5>My Points</h5>
                <div></div>
            </div>
        </div>
    </nav>
</header>

<body class="bg-body-tertiary">
    <div class="container py-4">
        <div class="border rounded-3 border-primary p-3 text-center bg-white">
            <i class="fa-solid fa-star mb-2"></i>
            <h2><?= (int) $points ?></h2>
            <span>Total Points</span>
        </div>

        <div class="d-flex gap-3 my-3">
            <div class="border rounded-3 border-primary p-3 w-50 bg-white">
                <i class="fa-solid fa-arrow-trend-up mb-2"></i>
                <h3>Top <?= $topPercent ?>%</h3>
                <span>Ahead of <?= $aheadPercent ?>% of users</span>
            </div>
            <div class="border rounded-3 border-primary p-3 w-50 bg-white">
                <i class="fa-solid fa-shapes mb-2"></i>
                <h3><?= (int) $activeAreas ?></h3>
                <span>Active Areas</span>
            </div>
        </div>

        <div class="border rounded-3 border-primary p-3 bg-white">
            <div class="d-flex gap-3 align-items-center">
                <div class="rounded-3 bg-primary d-flex justify-content-center align-items-center" style="width: 50px; height: 50px">
                    <i class="fa-solid fa-trophy text-white"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= htmlspecialchars($levelInfo['name']) ?></h3>
                    <span class="text-muted small">Current Level</span>
                </div>
            </div>

            <div class="mt-3 mb-2">
                <p class="m-0">
                    Progress to <?= htmlspecialchars($levelInfo['next_name'] ?? $levelInfo['name']) ?>
                    <span>
                        <?= $levelInfo['next_name'] ? $nextLevelPoints . ' points to go' : 'Max level reached' ?>
                    </span>
                </p>

                <div class="progress mt-2" style="height: 10px">
                    <div class="progress-bar" style="width: <?= (int) $levelInfo['progress_to_next'] ?>%"></div>
                </div>
            </div>
        </div>

        <div class="border rounded-3 border-primary p-3 bg-white mt-3">
            <div class="d-flex flex-column align-items-center">
                <div class="d-flex gap-2 align-items-center">
                    <i class="fa-solid fa-ranking-star"></i>
                    <h4 class="mb-0">Top Performers</h4>
                </div>

                <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                    <?php
                    $topThree = array_slice($leaderboard, 0, 3);
                    foreach ($topThree as $index => $user):
                        $rank = $index + 1;
                    ?>
                        <div class="bg-warning rounded-3 p-3 d-flex flex-column align-items-center" style="min-width: 180px;">
                            <div class="rounded-circle mt-4 mx-5 bg-primary d-flex justify-content-center align-items-center" style="width: 50px; height: 50px">
                                <i class="fa-solid fa-user text-white"></i>
                            </div>
                            <h6 class="mt-2 mb-1">
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                            </h6>
                            <span class="text-muted small"><?= (int) $user['points'] ?> pts</span>
                            <h1 class="mb-0 mt-3"><?= $rank ?></h1>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 rounded-3 border border-primary p-3">
                <div>
                    <span class="text-muted small">Total points</span>
                    <h5 class="mb-0 mt-1">You (<?= (int) $points ?> pts)</h5>
                </div>
                <h6 class="text-muted">
                    <?= $currentUserRank > 0 ? 'Rank ' . $currentUserRank : 'No rank yet' ?>
                </h6>
            </div>

            <br>
            <h6 class="text-muted">Player Ranks</h6>
            <ul class="list-unstyled m-0">
                <?php
                $others = array_slice($leaderboard, 3, 7);
                if (empty($others)):
                ?>
                    <li class="rounded-3 border border-primary p-3 text-center text-muted">No more users yet.</li>
                <?php else: ?>
                    <?php foreach ($others as $index => $user): ?>
                        <?php $rank = $index + 4; ?>
                        <li class="d-flex justify-content-between align-items-center rounded-3 border border-primary p-3 mb-3">
                            <div class="d-flex gap-3 align-items-center">
                                <h3 class="m-0"><?= $rank ?></h3>
                                <div class="rounded-circle bg-primary d-flex justify-content-center align-items-center" style="width: 70px; height: 40px;">
                                    <i class="fa-solid fa-user text-white"></i>
                                </div>
                                <div class="w-100">
                                    <h6 class="mb-1"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h6>
                                    <span class="text-muted small">
                                        <?= htmlspecialchars(getLevelInfo((int) $user['points'])['name']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <i class="fa-solid fa-star"></i>
                                <span><?= (int) $user['points'] ?> pts</span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</body>