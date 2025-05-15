<?php
function toast($type, $message)
{
    $_SESSION['toast'] = [
        'type' => $type,
        'message' => $message
    ];
}

if (isset($_SESSION['toast'])):
?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Inline styles for dynamic toast background -->
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const type = '<?= $_SESSION['toast']['type'] ?>';
            const message = '<?= addslashes($_SESSION['toast']['message']) ?>';

            Swal.fire({
                icon: type,
                title: message,
                toast: true,
                position: 'top-end',
                timer: 3000,
                showConfirmButton: false,
                timerProgressBar: true,
                customClass: {
                    popup: `toast-${type}`
                }
            });
        });
    </script>
    <?php unset($_SESSION['toast']); ?>
<?php endif; ?>