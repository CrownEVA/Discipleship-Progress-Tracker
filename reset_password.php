<?php
session_start();
date_default_timezone_set('Asia/Manila');

include 'links.php';
include 'database.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = "";

if ($token === '') {
    die("Invalid reset link.");
}

$tokenHash = hash('sha256', $token);

$stmt = $conn->prepare("
    SELECT *
    FROM password_reset_tokens
    WHERE token_hash = ?
      AND used_at IS NULL
      AND expires_at >= NOW()
    LIMIT 1
");
$stmt->execute([$tokenHash]);
$resetRow = $stmt->fetch();

if (!$resetRow) {
    die("Reset link is invalid or expired.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $resetRow['user_id']]);

        $stmt = $conn->prepare("
            UPDATE password_reset_tokens
            SET used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$resetRow['id']]);

        $_SESSION["success"] = "Password reset successful. Please log in.";
        header("Location: login.php");
        exit;
    }
}
?>

<div class="d-flex justify-content-center align-items-center vh-100" style="background-image: url('assets/bg.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    <div class="border rounded-3 border-primary p-4 w-25 bg-white">
        <h3 class="m-0">Reset Password</h3>
        <br>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-group mb-3">
                <label>New Password</label>
                <input type="password" name="password" class="form-control" placeholder="New password" required>
            </div>

            <div class="form-group mb-3">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
            <div class="text-center mt-2">
                <a href="login.php" class="text-decoration-none">Back to Login</a>
            </div>
        </form>
    </div>
</div>