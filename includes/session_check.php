<?php
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 3600)) {
    session_unset();
    session_destroy();

    echo "<script>
    alert('Session expired. You will be logged out.');
    window.location.href = 'logout.php';
</script>";
    exit;
}
