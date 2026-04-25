<?php
session_start();
date_default_timezone_set('Asia/Manila');

include 'links.php';
include 'database.php';

$errors = [];
$email = "";

if (isset($_SESSION["user_id"])) {
    header("Location: home.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';

    if ($email === "") {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if ($password === "") {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["first_name"] . " " . $user["last_name"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["user_role"] = $user["role"];

            header("Location: home.php");
            exit;
        } else {
            $errors[] = "Invalid email or password.";
        }
    }
}
?>

<div class="d-flex justify-content-center align-items-center vh-100 bg-primary">
    <div class="border rounded-3 border-primary p-4 w-25 bg-white">
        <h3 class="m-0">Sign In</h3>
        <br>

        <?php if (isset($_SESSION["success"])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION["success"]) ?>
            </div>
            <?php unset($_SESSION["success"]); ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" placeholder="Enter email">
            </div>
            <br>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="Password">
            </div>
            <div class="text-end">
                <a href="forgot_password.php" class="text-decoration-none text-muted small">Forgot Password?</a>
            </div>
            <br>

            <button type="submit" class="btn btn-primary w-100">Submit</button>
            <div class="text-center mt-2">
                <p class="p-0 m-0">Don't have an account? <a href="register.php">Sign Up</a></p>
            </div>
        </form>
    </div>
</div>