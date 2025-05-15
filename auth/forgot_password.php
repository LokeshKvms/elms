<?php
session_start();
require dirname(__DIR__) . '/config.php';
require INCLUDES_PATH . '/db.php';
require INCLUDES_PATH . '/mail.php';

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ".BASE_URL."/admin/admin_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'employee') {
        header("Location: " . BASE_URL . "/employee/user_dashboard.php");
        exit;
    }
}

$showOtpField = false;
$email = $_POST['email'] ?? '';
$otp_input = $_POST['otp'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        $_SESSION['forget'] = true;
        $email = $_SESSION['reset_email'] ?? '';
        if (!empty($email)) {
            $otp = rand(100000, 999999);
            $expires = time() + 300;

            $stmt = $conn->prepare("UPDATE Employees SET otp = ?, otp_expires = ? WHERE email = ?");
            $stmt->bind_param("iis", $otp, $expires, $email);
            if ($stmt->execute()) {
                try {
                    sendmail($email, 'Your OTP for Login', "<h3>Your new OTP is: <strong>$otp</strong></h3>");
                    $message = "<script>setTimeout(() => showToast('OTP resent successfully.', 'success'), 100);</script>";
                    $showOtpField = true;
                } catch (Exception $e) {
                    $message = "<script>setTimeout(() => showToast('Failed to resend OTP.', 'danger'), 100);</script>";
                }
            }
        }
    } else {
        $_SESSION['forget'] = true;
        $stmt = $conn->prepare("SELECT * FROM Employees WHERE email = ? AND email !='admin@gmail.com'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $_SESSION['reset_email'] = $email;

            if (empty($otp_input)) {
                $otp = rand(100000, 999999);
                $expires = time() + 300;

                $update = $conn->prepare("UPDATE Employees SET otp = ?, otp_expires = ? WHERE email = ?");
                $update->bind_param("iis", $otp, $expires, $email);
                $update->execute();

                try {
                    sendmail($email, 'Your OTP for Login', "<h3>Your new OTP is: <strong>$otp</strong></h3>");
                    $message = "<script>setTimeout(() => showToast('OTP sent to your email.', 'success'), 100);</script>";
                    $showOtpField = true;
                } catch (Exception $e) {
                    $message = "<script>setTimeout(() => showToast('Failed to send OTP.', 'danger'), 100);</script>";
                }
            } else {
                // Refetch latest OTP data
                $refreshStmt = $conn->prepare("SELECT otp, otp_expires FROM Employees WHERE email = ?");
                $refreshStmt->bind_param("s", $email);
                $refreshStmt->execute();
                $otpResult = $refreshStmt->get_result();
                $otpData = $otpResult->fetch_assoc();

                $storedOtp = $otpData['otp'];
                $expiresAt = $otpData['otp_expires'];

                if ($otp_input == $storedOtp && time() < $expiresAt) {
                    $clear = $conn->prepare("UPDATE Employees SET otp = NULL, otp_expires = NULL WHERE email = ?");
                    $clear->bind_param("s", $email);
                    $clear->execute();

                    header("Location: reset_password.php");
                    exit;
                } else {
                    $showOtpField = true;
                    $message = "<script>setTimeout(() => showToast('Invalid or expired OTP.', 'danger'), 100);</script>";
                }
            }
        } else {
            $message = "<script>setTimeout(() => showToast('Email not found.', 'danger'), 100);</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script>
        let resendBtn;
        let timer;

        function startTimer() {
            resendBtn = document.getElementById("resendBtn");
            if (!resendBtn) return;
            resendBtn.disabled = true;
            let timeLeft = 5;
            timer = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    resendBtn.disabled = false;
                    resendBtn.innerText = "Resend OTP";
                } else {
                    resendBtn.innerText = "Resend in " + timeLeft + "s";
                    timeLeft--;
                }
            }, 1000);
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast position-fixed top-0 end-0 m-3 text-white bg-${type} show`;
            toast.innerHTML = `<div class="toast-body">${message}</div>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        window.onload = startTimer;
    </script>
</head>

<body class="d-flex align-items-center justify-content-center min-vh-100" style="background-color:#F5F7FA;">
    <?= $message ?? '' ?>

    <div class="card p-5 shadow-lg" style="width: 100%; max-width: 400px;">
        <h4 class="mb-3 text-center">Forgot Password</h4>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Email:</label>
                <input id="email" type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email) ?>">
            </div>

            <?php if ($showOtpField): ?>
                <script>
                    document.getElementById('email').setAttribute('readonly', true);
                </script>

                <div class="mb-3">
                    <label>Enter OTP:</label>
                    <input type="text" name="otp" class="form-control" required>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-dark w-100 mb-3">
                <?= $showOtpField ? "Verify OTP" : "Send OTP" ?>
            </button>
        </form>

        <?php if ($showOtpField): ?>
            <form method="POST" class="text-center">
                <button type="submit" name="resend" id="resendBtn" class="btn btn-link">Resend OTP</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>