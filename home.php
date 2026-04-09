<?php
session_start();
include 'links.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
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
                <h4 class="m-0 py-1">10</h4>
                <span class="text-black">Points</span>
            </div>
            <div class="d-flex flex-column align-items-center border-end border-primary w-100">
                <i class="fa-solid fa-fire mb-1"></i>
                <h4 class="m-0 py-1">1 days</h4>
                <span class="text-black">Streak</span>
            </div>
            <div class="d-flex flex-column align-items-center w-100">
                <i class="fa-solid fa-trophy"></i>
                <h4 class="m-0 py-1">New Believers</h4>
                <span class="text-black">Level</span>
            </div>
        </div>
    </a>
    <div class="container p-0 mt-4">
        <div id="carouselExampleAutoplaying" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="slide-one.png" class="d-block w-100 h-25 rounded-3" alt="...">
                </div>
                <div class="carousel-item">
                    <img src="slide-two.png" class="d-block w-100 h-25 rounded-3" alt="...">
                </div>
                <div class="carousel-item">
                    <img src="slide-three.png" class="d-block w-100 h-25 rounded-3" alt="...">
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
    
    <div class="container mt-4 border rounded-3 border-primary p-4 bg-white">
        <div class="container-header d-flex justify-content-between align-items-center border-bottom border-muted pb-3">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-flag text-primary h5 p-0 m-0"></i>
                <h5 class="m-0">Journeys</h5>
            </div>
            <a href="journeys.php" class="btn text-primary">View all</a>
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
            <li class="list-group-item px-0 py-3">
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
    </a>
    
</body>