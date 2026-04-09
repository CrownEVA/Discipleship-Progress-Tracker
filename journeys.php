<?php
session_start();
include 'links.php';
include 'database.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$search = trim($_GET["q"] ?? '');
$searchLike = '%' . $search . '%';

/* Discover journeys */
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
$discoverJourneys = $stmt->fetchAll();

/* My journeys */
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
    GROUP BY j.id, j.image, j.title, j.description, uj.status, uj.progress_percent, uj.id
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
</style>

<header>
    <nav class="sticky-top bg-white pt-3">
        <div class="container">
            <div class="align-items-center d-flex justify-content-between mb-3">
                <a href="home.php">
                    <i class="fa-solid fa-chevron-left text-black"></i>
                </a>
                <h5 class="mb-0 text-primary">Journeys</h5>
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
                <div class="row g-0">
                    <div class="col-md-2">
                        <img src="<?= htmlspecialchars($journey['image']) ?>" class="img-fluid rounded-start w-100" alt="Journey image">
                    </div>
                    <div class="col-md-10">
                        <div class="card-body d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <h5 class="card-title mb-2"><?= htmlspecialchars($journey['title']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars($journey['description']) ?></p>

                                <div class="d-flex gap-3 flex-wrap">
                                    <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                        <i class="fa-solid fa-list-ol"></i>
                                        <span><?= (int) $journey['lesson_count'] ?> lessons</span>
                                    </div>
                                    <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                        <i class="fa-solid fa-user-group"></i>
                                        <span><?= (int) $journey['enrolled_users'] ?> users</span>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center" style="width: 40px; height: 40px;">
                                <i class="fa-solid fa-plus"></i>
                            </div>
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
                        <img id="discoverModalImage" src="" class="img-fluid w-100 rounded-start" alt="Journey image">
                        <div class="mt-3">
                            <h4 class="card-title" id="discoverModalTitle"></h4>

                            <div class="d-flex gap-3 mt-2 mb-3">
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-list-ol"></i>
                                    <span id="discoverModalLessons"></span>
                                </div>
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-user-group"></i>
                                    <span id="discoverModalUsers"></span>
                                </div>
                            </div>

                            <div>
                                <h5>Description</h5>
                                <p id="discoverModalDescription"></p>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer p-3">
                        <form method="POST" action="enroll_journey.php" class="w-100 m-0">
                            <input type="hidden" name="journey_id" id="discoverJourneyId">
                            <button type="submit" class="btn btn-primary w-100" id="discoverModalButton">Start Journey</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4 journey-section" id="myJourneysSection">
        <?php if (empty($myJourneys)): ?>
            <div class="alert alert-light border">You have no journeys yet.</div>
        <?php endif; ?>

        <?php foreach ($myJourneys as $journey): ?>
            <?php
                $progress = (int) $journey['progress_percent'];
            ?>
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
                data-users="0"
                data-progress="<?= $progress ?>"
            >
                <div class="row g-0">
                    <div class="col-md-2">
                        <img src="<?= htmlspecialchars($journey['image']) ?>" class="img-fluid rounded-start w-100" alt="Journey image">
                    </div>
                    <div class="col-md-10">
                        <div class="card-body d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <h5 class="card-title mb-2"><?= htmlspecialchars($journey['title']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars($journey['description']) ?></p>

                                <div class="d-flex gap-3 flex-wrap">
                                    <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                        <i class="fa-solid fa-list-ol"></i>
                                        <span><?= (int) $journey['lesson_count'] ?> lessons</span>
                                    </div>
                                    <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                        <i class="fa-solid fa-user-group"></i>
                                        <span><?= $progress ?>%</span>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <span class="text-muted small"><?= $progress ?>% complete</span>
                                    <div class="progress mt-2" style="height: 5px">
                                        <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center" style="width: 40px; height: 40px;">
                                <i class="fa-solid fa-chevron-right"></i>
                            </div>
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
                        <img id="myModalImage" src="" class="img-fluid w-100 rounded-start" alt="Journey image">
                        <div class="mt-3">
                            <h4 class="card-title" id="myModalTitle"></h4>

                            <div class="d-flex gap-3 mt-2 mb-3">
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-list-ol"></i>
                                    <span id="myModalLessons"></span>
                                </div>
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-user-group"></i>
                                    <span id="myModalProgress"></span>
                                </div>
                            </div>

                            <div class="border rounded-3 border-primary p-3 my-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="m-0">Your Progress</h5>
                                    <h5 class="m-0" id="myModalProgressText"></h5>
                                </div>
                                <div class="progress mt-2" style="height: 10px">
                                    <div class="progress-bar" id="myModalProgressBar" style="width: 0%"></div>
                                </div>
                            </div>

                            <div>
                                <h5>Description</h5>
                                <p id="myModalDescription"></p>
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
        document.getElementById('discoverModalButton').textContent = 'Start Journey';
    });
});

const myJourneyModal = document.getElementById('myJourneyModal');

myJourneyModal.addEventListener('show.bs.modal', function (event) {
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