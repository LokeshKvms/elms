<?php
session_start();
require dirname(__DIR__) . '/config.php';
require INCLUDES_PATH . '/db.php';
require INCLUDES_PATH . '/toast.php';

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: " . BASE_URL . "/admin/admin_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'employee') {
        header("Location: " . BASE_URL . "/employee/user_dashboard.php");
        exit;
    }
}

if (!isset($_SESSION['forget'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($newPassword !== $confirmPassword) {
        echo "<script>setTimeout(() => showToast('Passwords do not match.', 'error'), 100);</script>";
    } else {
        $email = $_SESSION['reset_email'];
        $hashPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE Employees SET password = ?, otp = NULL, otp_expires = NULL WHERE email = ?");
        $stmt->bind_param("ss", $hashPassword, $email);
        if ($stmt->execute()) {
            unset($_SESSION['reset_email']);

            // Auto-login user
            $stmt = $conn->prepare("SELECT * FROM Employees WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            $_SESSION['email'] = $email;
            $_SESSION['user_id'] = $user['employee_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = ($email === 'admin@gmail.com') ? 'admin' : 'employee';
            $_SESSION['login_time'] = time();
            $redirect = ($email === 'admin@gmail.com')
                ? BASE_URL . '/admin/admin_dashboard.php'
                : BASE_URL . '/employee/user_dashboard.php';
            toast('success', 'Password reset successful. Welcome to your dashboard.');
            header("Location: $redirect");
            exit;
        } else {
            echo "<script>setTimeout(() => showToast('Failed to reset password.', 'error'), 100);</script>";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="icon" href="../favicon.ico">
    <style>
        .toast-success {
            background-color: #28a745 !important;
            color: white !important;
        }

        .toast-error {
            background-color: #dc3545 !important;
            color: white !important;
        }

        .toast-warning {
            background-color: #DE7E5D !important;
            color: white !important;
        }

        .toast-info {
            background-color: #17a2b8 !important;
            color: white !important;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function showToast(message, type) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type,
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                customClass: {
                    popup: `toast-${type}`
                }
            });
        }
    </script>
</head>

<body class="d-flex align-items-center justify-content-center min-vh-100" style="background-color:#F5F7FA;">
    <div class="card p-5 shadow" style="width: 100%; max-width: 400px;">
        <h4 class="mb-3 text-center">Reset Password</h4>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">New Password:</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password:</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="my-3 btn btn-dark w-100">Reset Password</button>
        </form>
    </div>
</body>

</html>