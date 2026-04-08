<?php include 'links.php'; ?>

<header>
    <nav class="sticky-top bg-body-tertiary">
        <div class="container">
            <div class="align-items-center d-flex justify-content-between">
                <a href="home.php">
                    <i class="fa-solid fa-chevron-left"></i>            
                </a>
                <h5>Journeys</h5>
            </div>
            <form class="d-flex" role="search">
                <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search"/>
                <button class="btn btn-outline-success" type="submit">Search</button>
            </form>
            <ul class="navbar-nav d-flex flex-row justify-content-between">
                <li class="nav-item w-100 text-center">
                    <a class="nav-link active" aria-current="page" href="#discover">Discover</a>
                </li>
                <li class="nav-item w-100 text-center">
                    <a class="nav-link" href="#">My Journeys</a>
                </li>
            </ul>
        </div>
    </nav>
</header>

<body>
    <div class="container mt-4" id="discover">
        <div class="card mb-3" data-bs-toggle="modal" data-bs-target="#journaldetails" style="cursor: pointer;">
            <div class="row g-0">
                <div class="col-md-2">
                    <img src="sample.jpg" class="img-fluid rounded-start">
                </div>
                <div class="col-md-10">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">A Life That Pleases God</h5>
                            <p class="card-text">Understand holiness, godly character, and how to live differently by grace in a broken world.</p>
                            <div class="d-flex gap-3">
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-list-ol"></i>
                                    <span>8 Steps</span>
                                </div>
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-user-group"></i>
                                    <span>17 Users</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <i class="fa-solid fa-plus"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="journaldetails" tabindex="-1" aria-labelledby="journaldetailsLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="journaldetailsLabel">Journal Details</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <img src="sample.jpg" class="img-fluid rounded-start">
                        <div>
                            <h4 class="card-title">A Life That Pleases God</h4>
                            <div class="d-flex gap-3">
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-list-ol"></i>
                                    <span>8 lessons</span>
                                </div>
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-user-group"></i>
                                    <span>17 users</span>
                                </div>
                            </div>
                            <div>
                                <h5>Description</h5>
                                <p>Understand holiness, godly character, and how to live differently by grace in a broken world.</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="content.php"><button type="button" class="btn btn-primary w-100">Start Journey</button></a>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="container mt-4" id="myjourneys">
        <div class="card mb-3" data-bs-toggle="modal" data-bs-target="#journaldetailsmj" style="cursor: pointer;">
            <div class="row g-0">
                <div class="col-md-2">
                    <img src="sample.jpg" class="img-fluid rounded-start">
                </div>
                <div class="col-md-10">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">A Life That Pleases God</h5>
                            <p class="card-text">Understand holiness, godly character, and how to live differently by grace in a broken world.</p>
                            <div class="d-flex gap-3">
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-list-ol"></i>
                                    <span>8 Steps</span>
                                </div>
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-user-group"></i>
                                    <span>17 Users</span>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="text-muted small">12% complete</span>
                                <div class="progress mt-2" role="progressbar" aria-label="Example 1px high" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100" style="height: 5px">
                                    <div class="progress-bar" style="width: 25%"></div>
                                </div>
                            </div>
                           
                        </div>
                        <div>
                            <i class="fa-solid fa-chevron-right"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="journaldetailsmj" tabindex="-1" aria-labelledby="journaldetailsLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="journaldetailsLabel">Journal Details</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <img src="sample.jpg" class="img-fluid rounded-start">
                        <div>
                            <h4 class="card-title">A Life That Pleases God</h4>
                            <div class="d-flex gap-3">
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-list-ol"></i>
                                    <span>8 lessons</span>
                                </div>
                                <div class="py-2 px-3 rounded-5 bg-primary text-white">
                                    <i class="fa-solid fa-user-group"></i>
                                    <span>17 users</span>
                                </div>
                            </div>
                            <div class="border rounded-3 border-primary p-3 my-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="m-0">Your Progress</h5>
                                    <h5 class="m-0">12%</h5>
                                </div>
                                <div class="progress mt-2" role="progressbar" aria-label="Example 1px high" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100" style="height: 10px">
                                    <div class="progress-bar" style="width: 25%"></div>
                                </div>
                                <span class="text-muted small">Step 1 of 8</span>
                            </div>
                            <div>
                                <h5>Description</h5>
                                <p>Understand holiness, godly character, and how to live differently by grace in a broken world.</p>
                            </div>
                        </div>
                    </div>
                   <div class="modal-footer d-flex gap-2">
                        <a href="content.php"><button type="button" class="btn btn-primary flex-fill">Continue Journey</button></a>
                        <button type="button" class="btn btn-danger flex-fill">Unenroll</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>