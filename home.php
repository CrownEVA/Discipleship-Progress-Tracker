<?php
session_start();
include 'links.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
?>

<header>
    <nav class="navbar bg-body-tertiary">
        <div class="container">
            <a class="navbar-brand" href="account.php">
                <img src="/docs/5.3/assets/brand/bootstrap-logo.svg" alt="Bootstrap" width="30" height="24">
            </a>
            <div class="align-items-center d-flex flex-column">
                <h5 class="m-0">Welcome, <?= htmlspecialchars($_SESSION["user_name"]) ?>!</h5>
                <span>Are you ready to win souls day?</span>
            </div>
            <a href=""><i class="fa-regular fa-bell"></i></a>
        </div>
    </nav>
</header>

<body>
    <a href="reports.php">
        <div class="container mt-4 border rounded-3 border-primary p-4 d-flex justify-content-around">
            <div class="d-flex flex-column align-items-center border-end border-primary w-100">
                <i class="fa-solid fa-star mb-1"></i>
                <h5 class="m-0">10</h5>
                <span>Points</span>
            </div>
            <div class="d-flex flex-column align-items-center border-end border-primary w-100">
                <i class="fa-solid fa-fire mb-1"></i>
                <h5 class="m-0">1 days</h5>
                <span>Streak</span>
            </div>
            <div class="d-flex flex-column align-items-center w-100">
                <i class="fa-solid fa-trophy"></i>
                <h5 class="m-0">New Believers</h5>
                <span>Level</span>
            </div>
        </div>
    </a>
    <div class="container mt-4 border rounded-3 border-primary p-4">
        <div class="container-header d-flex justify-content-between align-items-center mb-3 border-bottom border-primary pb-3">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-flag"></i>
                <h5 class="m-0">Journeys</h5>
            </div>
            <a href="journeys.php" class="btn">View all</a>
        </div>
        <ul class="list-group list-group-flush">
            <li class="list-group-item py-3 px-0">
                <h6>Life in the Power of the Holy Spirit</h6>
                <span class="text-muted small">12% complete</span>
                <div class="progress my-3" role="progressbar" aria-label="Example 1px high" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100" style="height: 5px">
                    <div class="progress-bar" style="width: 25%"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Step 1 of 8</span>
                    <a href="journeys.php" class="btn btn-primary">Continue</a>
                </div>
            </li>
            <li class="list-group-item py-3 px-0">
                <h6>Life in the Power of the Holy Spirit</h6>
                <span class="text-muted small">Not started</span>
                <div class="progress my-3" role="progressbar" aria-label="Example 1px high" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100" style="height: 5px">
                    <div class="progress-bar" style="width: 0%"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">9 steps</span>
                    <a href="journeys.php" class="btn btn-primary">Start</a>
                </div>
            </li>
        </ul>
    </div>
    <a href="journeys.php">
        <div class="container mt-4 border rounded-3 border-primary p-4">
            <div class="d-flex justify-content-between align-items-center">
                <i class="fa-solid fa-compass"></i>
                <div>
                    <h5 class="m-0">Start Your Journey</h5>
                    <span class="text-muted small">Discover spiritual growth paths</span>
                </div>
            <i class="fa-solid fa-chevron-right"></i>
        </div>
    </a>
    
</body>