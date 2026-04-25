<?php
session_start();
date_default_timezone_set('Asia/Manila');

include 'links.php';
include 'database.php';
include 'mailer.php';

$message = "";
$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? '');

    if ($email === "") {
        $error = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $conn->prepare("
                INSERT INTO password_reset_tokens (user_id, email, token_hash, expires_at)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user['id'], $email, $tokenHash, $expiresAt]);

            $resetLink = "http://localhost/Discipleship-Progress-Tracker/reset_password.php?token=" . urlencode($token);

            $subject = "Reset your password";
            $html = "
                <p>Hello " . htmlspecialchars($user['first_name']) . ",</p>
                <p>Click the link below to reset your password.</p>
                <p><a href='{$resetLink}'>Reset Password</a></p>
                <p>This link expires in 1 hour.</p>
            ";

            sendMail($email, $subject, $html);
        }

        $message = "If the email exists, a password reset link has been sent.";
    }
}
?>

<div class="d-flex justify-content-center align-items-center vh-100" style="background-image: url('assets/bg.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    <div class="border rounded-3 border-primary p-4 w-25 bg-white">
        <h3 class="m-0">Forgot Password</h3>
        <br>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email</label>
                <input 
                    type="email" 
                    name="email" 
                    class="form-control" 
                    value="<?= htmlspecialchars($email) ?>" 
                    placeholder="Enter your email"
                    required
                >
            </div>
            <br>

            <button type="submit" class="btn btn-primary w-100">
                Send Reset Link
            </button>

            <div class="text-center mt-2">
                <a href="login.php" class="text-decoration-none">
                    Back to Login
                </a>
            </div>
        </form>
    </div>
</div>