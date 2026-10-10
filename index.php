<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT u.user_id, u.username, u.password_hash, u.full_name, r.role_name 
                                FROM users u 
                                JOIN roles r ON u.role_id = r.role_id 
                                WHERE u.username = ? AND u.is_active = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify Password
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role_name'] = $user['role_name'];

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "මුරපදය (Password) වැරදියි!";
            }
        } else {
            $error = "පරිශීලක නාමය (Username) හමු නොවීය!";
        }
    } else {
        $error = "කරුණාකර ක්ෂේත්‍ර දෙකම සම්පූර්ණ කරන්න.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .login-card { max-width: 400px; margin-top: 100px; border-radius: 10px; }
   <?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT u.user_id, u.username, u.password_hash, u.full_name, r.role_name 
                                FROM users u 
                                JOIN roles r ON u.role_id = r.role_id 
                                WHERE u.username = ? AND u.is_active = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Direct comparison (Temporary testing)
            if ($password == $user['password_hash']) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role_name'] = $user['role_name'];

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "මුරපදය (Password) වැරදියි!";
            }
        } else {
            $error = "පරිශීලක නාමය (Username) හමු නොවීය!";
        }
    } else {
        $error = "කරුණාකර ක්ෂේත්‍ර දෙකම සම්පූර්ණ කරන්න.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSIMS - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .login-card { max-width: 400px; margin-top: 100px; border-radius: 10px; }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="card shadow-lg p-4 login-card w-100">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary">🏢 OSIMS Login</h3>
            <p class="text-muted">Office Store Management System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2" role="alert">
                <?= $error; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label font-weight-bold">Username</label>
                <input type="text" name="username" id="username" class="form-control" required placeholder="Type username">
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required placeholder="Type password">
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-bold mt-2">Sign In</button>
        </form>
    </div>
</div>

</body>
</html>