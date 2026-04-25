<?php
session_start();
date_default_timezone_set('Asia/Manila');

include 'links.php';
include 'database.php';
include 'mailer.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT id, first_name, last_name, email, role, points
    FROM users
    WHERE id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
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

function sendPasswordResetLink(PDO $conn, int $userId, string $email, string $firstName): bool
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $conn->prepare("
        INSERT INTO password_reset_tokens (user_id, email, token_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $email, $tokenHash, $expiresAt]);

    $resetLink = "http://localhost/Discipleship-Progress-Tracker/reset_password.php?token=" . urlencode($token);

    $subject = "Reset your password";
    $html = "
        <p>Hello " . htmlspecialchars($firstName) . ",</p>
        <p>Click the link below to reset your password.</p>
        <p><a href='{$resetLink}'>Reset Password</a></p>
        <p>This link expires in 1 hour.</p>
    ";

    return sendMail($email, $subject, $html);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];

    if ($action === "update_profile") {
        $firstName = trim($_POST["first_name"] ?? '');
        $lastName = trim($_POST["last_name"] ?? '');
        $email = trim($_POST["email"] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '') {
            $_SESSION["error"] = "All fields are required.";
            header("Location: account.php");
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION["error"] = "Invalid email format.";
            header("Location: account.php");
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $_SESSION["error"] = "Email is already in use.";
            header("Location: account.php");
            exit;
        }

        $oldEmail = $user['email'];

        $stmt = $conn->prepare("
            UPDATE users
            SET first_name = ?, last_name = ?
            WHERE id = ?
        ");
        $stmt->execute([$firstName, $lastName, $userId]);

        $_SESSION["user_name"] = $firstName . " " . $lastName;

        if ($email !== $oldEmail) {
            if (sendEmailChangeLink($conn, $userId, $email)) {
                $_SESSION["success"] = "Profile updated. A verification link was sent to your new email address.";
            } else {
                $_SESSION["error"] = "Profile updated, but email verification could not be sent.";
            }
        } else {
            $_SESSION["success"] = "Profile updated successfully.";
        }

        header("Location: account.php");
        exit;
    }

    if ($action === "send_password_reset") {
        if (sendPasswordResetLink($conn, $userId, $user['email'], $user['first_name'])) {
            $_SESSION["success"] = "Password reset link sent to your email.";
        } else {
            $_SESSION["error"] = "Unable to send password reset link.";
        }

        header("Location: account.php");
        exit;
    }

    if ($action === "delete_account") {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("DELETE FROM user_lesson_progress WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $conn->prepare("DELETE FROM user_journeys WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $conn->prepare("DELETE FROM email_change_requests WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);

            $conn->commit();

            session_destroy();
            header("Location: login.php");
            exit;
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION["error"] = "Unable to delete account.";
            header("Location: account.php");
            exit;
        }
    }
}
?>

<header>
    <nav class="sticky-top bg-white py-3">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="home.php">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <h5>My Account</h5>
                <div></div>
            </div>
        </div>
    </nav>
</header>

<body class="bg-body-tertiary">
    <div class="container my-4">
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

        <div class="d-flex align-items-center justify-content-between my-4 p-4 bg-white rounded border border-primary">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary d-flex justify-content-center align-items-center" style="width: 50px; height: 50px">
                    <i class="fa-solid fa-user text-white"></i>
                </div>
                <div>
                    <h4 class="mb-1"><?= htmlspecialchars($user["first_name"] . " " . $user["last_name"]) ?></h4>
                    <div class="d-flex gap-2 align-items-center text-muted small">
                        <i class="fa-regular fa-envelope"></i>
                        <span><?= htmlspecialchars($user["email"]) ?></span>
                    </div>
                </div>
            </div>
            <i class="fa-solid fa-pen large" data-bs-toggle="modal" data-bs-target="#updateAccountModal" role="button"></i>
        </div>

        <a href="reports.php" class="text-decoration-none text-dark d-flex align-items-center justify-content-between mb-4 p-4 bg-white rounded border border-primary">
            <div class="d-flex gap-2 align-items-center">
                <i class="fa-solid fa-star text-primary"></i>
                <span>My Points</span>
            </div>
            <i class="fa-solid fa-chevron-right"></i>
        </a>

        <a href="#" class="text-decoration-none text-dark d-flex align-items-center justify-content-between mb-4 p-4 bg-white rounded border border-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
            <div class="d-flex gap-2 align-items-center">
                <i class="fa-solid fa-lock text-primary"></i>
                <span>Change Password</span>
            </div>
            <i class="fa-solid fa-chevron-right"></i>
        </a>

        <a href="#" class="text-decoration-none text-dark d-flex align-items-center justify-content-between mb-4 p-4 bg-white rounded border border-primary" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
            <div class="d-flex gap-2 align-items-center">
                <i class="fa-solid fa-trash text-primary"></i>
                <span>Delete Account</span>
            </div>
            <i class="fa-solid fa-chevron-right"></i>
        </a>

        <a href="logout.php" class="text-decoration-none text-dark d-flex align-items-center justify-content-between mb-4 p-4 bg-white rounded border border-primary">
            <div class="d-flex gap-2 align-items-center">
                <i class="fa-solid fa-right-from-bracket text-primary"></i>
                <span>Logout</span>
            </div>
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    </div>

    <div class="modal fade" id="updateAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="mb-3">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user["first_name"]) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user["last_name"]) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user["email"]) ?>" required>
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

    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Change Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_password_reset">
                        <p class="mb-0">
                            We will send a password reset link to <?= htmlspecialchars($user["email"]) ?>.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Send Reset Link</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Delete Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_account">
                        <p class="mb-0">
                            Are you sure you want to delete your account? This action cannot be undone.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">Yes, Delete</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>