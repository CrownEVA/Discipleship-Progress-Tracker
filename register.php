<?php
session_start();
date_default_timezone_set('Asia/Manila');

include 'links.php';
include 'database.php';
include 'mailer.php';

$errors = [];
$success = "";
$first_name = "";
$last_name = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST["first_name"] ?? '');
    $last_name  = trim($_POST["last_name"] ?? '');
    $email      = trim($_POST["email"] ?? '');
    $password   = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';

    if ($first_name === "") {
        $errors[] = "First name is required.";
    } elseif (!preg_match("/^[a-zA-Z ]+$/", $first_name)) {
        $errors[] = "First name can only contain letters and spaces.";
    }

    if ($last_name === "") {
        $errors[] = "Last name is required.";
    } elseif (!preg_match("/^[a-zA-Z ]+$/", $last_name)) {
        $errors[] = "Last name can only contain letters and spaces.";
    }

    if ($email === "") {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();

        $stmt = $conn->prepare("SELECT id FROM pending_registrations WHERE email = ?");
        $stmt->execute([$email]);
        $pending = $stmt->fetch();

        if ($existingUser || $pending) {
            $errors[] = "Email is already registered or waiting for verification.";
        } else {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO pending_registrations
                (first_name, last_name, email, password_hash, token_hash, expires_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$first_name, $last_name, $email, $passwordHash, $tokenHash, $expiresAt]);

            $verifyLink = "http://localhost/Discipleship-Progress-Tracker/verify_registration.php?token=" . urlencode($token);

            $subject = "Verify your email";
            $message = "
                <p>Hello " . htmlspecialchars($first_name) . ",</p>
                <p>Please verify your email to complete registration.</p>
                <p><a href='{$verifyLink}'>Verify Account</a></p>
                <p>This link expires in 30 minutes.</p>
            ";

            sendMail($email, $subject, $message);

            $_SESSION["success"] = "We sent a verification link to your email. Please check your inbox.";
            header("Location: login.php");
            exit;
        }
    }
}
?>

<div class="d-flex justify-content-center align-items-center vh-100 bg-primary">
    <div class="border rounded-3 border-primary p-4 w-25 bg-white">
        <h3 class="m-0">Sign Up</h3>
        <br>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($first_name) ?>" placeholder="Enter first name">
            </div>
            <br>

            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($last_name) ?>" placeholder="Enter last name">
            </div>
            <br>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" placeholder="Enter email">
            </div>
            <br>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="Password">
            </div>
            <br>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password">
            </div>
            <br>

            <button type="submit" class="btn btn-primary w-100">Submit</button>
            <div class="text-center mt-2">
                <p class="p-0 m-0">Already have an account? <a href="login.php">Sign In</a></p>
            </div>
        </form>
    </div>
</div>