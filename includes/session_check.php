<?php
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 3600)) {
    session_unset();
    session_destroy();

    echo "
    <html>
    <head>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Session expired',
                text: 'You will be logged out.',
                confirmButtonText: 'OK',
            }).then(() => {
                window.location.href = 'http://localhost/elms/';
            });
        </script>
    </body>
    </html>";
    exit;
}
