<?php
session_start();
include 'links.php';
include 'database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

function formatNotifDate(string $datetime): string
{
    return date('F j, Y h:i A', strtotime($datetime));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];
    $notificationId = (int) ($_POST["notification_id"] ?? 0);

    if ($notificationId > 0) {
        if ($action === "mark_read") {
            $stmt = $conn->prepare("
                UPDATE notifications
                SET is_read = 1, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
        }

        if ($action === "delete_notification") {
            $stmt = $conn->prepare("
                DELETE FROM notifications
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
        }
    }

    header("Location: notifications.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT n.*, j.title AS journey_title
    FROM notifications n
    LEFT JOIN journeys j ON j.id = n.journey_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC, n.id DESC
");
$stmt->execute([$userId]);
$allNotifications = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT n.*, j.title AS journey_title
    FROM notifications n
    LEFT JOIN journeys j ON j.id = n.journey_id
    WHERE n.user_id = ? AND n.is_read = 0
    ORDER BY n.created_at DESC, n.id DESC
");
$stmt->execute([$userId]);
$unreadNotifications = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT n.*, j.title AS journey_title
    FROM notifications n
    LEFT JOIN journeys j ON j.id = n.journey_id
    WHERE n.user_id = ? AND n.is_read = 1
    ORDER BY n.created_at DESC, n.id DESC
");
$stmt->execute([$userId]);
$readNotifications = $stmt->fetchAll();

function renderNotificationCard(array $notif): void
{
    $isRead = (int) $notif['is_read'] === 1;
    $bgClass = $isRead ? 'bg-secondary' : 'bg-primary';
    $borderClass = $isRead ? 'border' : 'border border-primary';
    $title = htmlspecialchars($notif['title']);
    $message = htmlspecialchars($notif['message']);
    $time = formatNotifDate($notif['created_at']);
    $notifId = (int) $notif['id'];
    ?>
    <div class="d-flex gap-3 p-4 bg-white rounded-3 <?= $borderClass ?> mb-3">
        <div class="rounded-3 <?= $bgClass ?> d-flex justify-content-center align-items-center" style="width: 40px; height: 40px">
            <i class="fa-solid fa-bell text-white"></i>
        </div>
        <div class="flex-grow-1">
            <h5 class="mb-2"><?= $title ?></h5>
            <p class="mb-2"><?= $message ?></p>
            <div class="d-flex justify-content-between align-items-center text-muted small">
                <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($time) ?></span>
                <div class="d-flex gap-3">
                    <?php if (!$isRead): ?>
                        <form method="POST" action="" class="m-0 p-0">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="notification_id" value="<?= $notifId ?>">
                            <button type="submit" class="btn btn-link p-0 border-0 text-decoration-none text-dark">
                                <i class="fa-regular fa-circle-check cursor-pointer"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" action="" class="m-0 p-0">
                        <input type="hidden" name="action" value="delete_notification">
                        <input type="hidden" name="notification_id" value="<?= $notifId ?>">
                        <button type="submit" class="btn btn-link p-0 border-0 text-decoration-none text-dark">
                            <i class="fa-regular fa-trash-can cursor-pointer"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>

<style>
.active-tab {
    border-bottom: 2px solid #0d6efd;
}

.journey-section {
    display: none;
}

.journey-section.active {
    display: block;
}
</style>

<header>
    <nav class="sticky-top bg-white pt-3">
        <div class="container">
            <div class="align-items-center d-flex justify-content-between mb-3">
                <a href="home.php">
                    <i class="fa-solid fa-chevron-left text-black"></i>
                </a>
                <h5 class="mb-0 text-primary">Notifications</h5>
                <a href="notifications.php">
                    <i class="fa-solid fa-arrows-rotate text-black"></i>
                </a>
            </div>

            <ul class="navbar-nav d-flex flex-row justify-content-between">
                <li class="nav-item w-100 text-center">
                    <a class="nav-link py-3 journey-tab active-tab" href="#" data-target="allSection">All</a>
                </li>
                <li class="nav-item w-100 text-center">
                    <a class="nav-link py-3 journey-tab" href="#" data-target="unreadSection">Unread</a>
                </li>
                <li class="nav-item w-100 text-center">
                    <a class="nav-link py-3 journey-tab" href="#" data-target="readSection">Read</a>
                </li>
            </ul>
        </div>
    </nav>
</header>

<body class="bg-body-tertiary">
    <div class="container my-4">
        <div class="journey-section active" id="allSection">
            <?php if (empty($allNotifications)): ?>
                <div class="alert alert-light border">No notifications yet.</div>
            <?php else: ?>
                <?php foreach ($allNotifications as $notif): ?>
                    <?php renderNotificationCard($notif); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="journey-section" id="unreadSection">
            <?php if (empty($unreadNotifications)): ?>
                <div class="alert alert-light border">No unread notifications.</div>
            <?php else: ?>
                <?php foreach ($unreadNotifications as $notif): ?>
                    <?php renderNotificationCard($notif); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="journey-section" id="readSection">
            <?php if (empty($readNotifications)): ?>
                <div class="alert alert-light border">No read notifications.</div>
            <?php else: ?>
                <?php foreach ($readNotifications as $notif): ?>
                    <?php renderNotificationCard($notif); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

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
</script>
</body>