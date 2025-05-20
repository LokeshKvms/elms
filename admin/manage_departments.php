<?php
session_start();
require dirname(__DIR__) . '/config.php';
require INCLUDES_PATH . '/db.php';
require INCLUDES_PATH . '/toast.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_name = $_POST['department_name'];

    if (!empty($_POST['department_id'])) {
        $id = (int) $_POST['department_id'];
        $stmt = $conn->prepare("UPDATE departments SET name = ? WHERE department_id = ?");
        $stmt->bind_param("si", $department_name, $id);
        $success = $stmt->execute();
        $stmt->close();

        toast($success ? 'info' : 'error', $success ? 'Department updated successfully.' : 'Update failed.');
    } else {
        $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
        $stmt->bind_param("s", $department_name);
        $success = $stmt->execute();
        $stmt->close();

        toast($success ? 'success' : 'error', $success ? 'Department added successfully.' : 'Insert failed.');
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id === 9) {

        toast('error', 'This is default department for unassigned ones. Cannot delete it');

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    $conn->query("UPDATE employees SET department_id=9 WHERE department_id=$id");
    $success = $conn->query("DELETE FROM departments WHERE department_id = $id");

    toast($success ? 'success' : 'error', $success ? 'Department deleted successfully.' : 'Delete failed.');
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
include COMMON_PATH . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>List of Departments</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <!-- DataTables Core CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

    <!-- DataTables Buttons Extension CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables Core JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

    <!-- DataTables Buttons Extension JS -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

    <!-- JSZip (required for Excel export) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <!-- Include SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .dataTables_filter {
            margin-bottom: 1rem !important;
        }

        #theTable thead th {
            text-align: center !important;
        }

        #theTable tbody tr:nth-child(odd) {
            background-color: #191c24;
        }

        .swal2-popup.colored-toast {
            background-color: #DE7E5D;
            color: #f8f9fa;
            box-shadow: 0 0.5rem 1rem rgb(0 0 0 / 0.7);
            font-weight: 600;
            font-size: 1rem;
            min-width: 320px;
            padding: 1rem 1.5rem;
            border-radius: 0.375rem;
        }

        .swal2-popup.colored-toast .swal2-confirm {
            background-color: #dc3545;
            color: #fff;
            border: none;
            padding: 0.4rem 1.2rem;
            font-weight: 600;
            border-radius: 0.3rem;
            transition: background-color 0.3s ease;
        }

        .swal2-popup.colored-toast .swal2-confirm:hover {
            background-color: #b02a37;
        }

        .swal2-popup.colored-toast .swal2-cancel {
            background-color: #6c757d;
            color: #fff;
            border: none;
            padding: 0.4rem 1.2rem;
            font-weight: 600;
            border-radius: 0.3rem;
            transition: background-color 0.3s ease;
        }

        .swal2-popup.colored-toast .swal2-cancel:hover {
            background-color: #ffc107;
        }

        .swal2-popup.colored-toast .swal2-actions {
            gap: 0.75rem;
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content px-3">
                <form method="POST" id="departmentForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addDepartmentModalLabel">Add / Edit Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background-color:white !important;"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="department_id" id="department_id">
                        <div class="mb-3">
                            <label for="department_name" class="form-label">Department Name</label>
                            <input type="text" class="form-control" name="department_name" id="department_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toasts -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
        <div id="confirmToast" class="toast align-items-center text-bg-warning border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex justify-content-between">
                <div class="toast-body fw-semibold">Delete this Department?</div>
                <div class="d-flex align-items-center">
                    <a id="confirmDeleteBtn" href="#" class="btn btn-sm btn-light me-2">Yes</a>
                    <button type="button" class="btn btn-sm btn-outline-light me-2" data-bs-dismiss="toast">No</button>
                </div>
            </div>
        </div>
    </div>

    <main class="container mt-4 flex-grow-1">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>List of Departments</h3>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <button class="btn btn-dark text-semibold" onclick="openAddModal()">Add Department</button>
            <?php endif; ?>
        </div>

        <table id="theTable" class="table text-center table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>S.No</th>
                    <th>Department</th>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = 1;
                $result = $conn->query("SELECT * FROM departments");
                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                ?>
                        <tr>
                            <td class=' bg-transparent'><?= $i++ ?></td>
                            <td class=' bg-transparent'><?= $row['name'] ?></td>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <td class=' bg-transparent'>
                                    <button class="btn btn-sm btn-warning px-3 mx-1" onclick='editDepartment(<?= json_encode($row) ?>)'>Edit</button>
                                    <button class="btn btn-sm btn-danger mx-1" onclick="confirmDelete(<?= $row['department_id'] ?>)">Delete</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                <?php
                    endwhile;
                else:
                    echo "<tr><td colspan='4' class='text-center'>No departments found.</td></tr>";
                endif;
                ?>
            </tbody>
        </table>

    </main>

    <footer class="text-center mt-auto py-3 text-muted small">
        &copy; <?= date("Y") ?> Employee Leave Portal
    </footer>

    <script>
        function openAddModal() {
            document.getElementById('departmentForm').reset();
            document.getElementById('department_id').value = '';
            const modal = new bootstrap.Modal(document.getElementById('addDepartmentModal'));
            modal.show();
        }

        function editDepartment(data) {
            document.getElementById('department_id').value = data.department_id;
            document.getElementById('department_name').value = data.name;
            const modal = new bootstrap.Modal(document.getElementById('addDepartmentModal'));
            modal.show();
        }

        function confirmDelete(id) {
            const toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: true,
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                customClass: {
                    popup: 'colored-toast',
                },
                buttonsStyling: false,
                icon: 'warning',
                timer: 20000,
                timerProgressBar: true
            });

            toast.fire({
                title: 'Delete this department?',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete=' + id;
                }
            });
        }


        $('#theTable').DataTable({
            lengthChange: false,
            dom: 'Bfrtip',
            buttons: [{
                extend: 'excel',
                text: 'Export to Excel',
                action: function(e, dt, button, config) {
                    var rowCount = dt.rows({
                        search: 'applied'
                    }).count();

                    if (rowCount === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No data available to export',
                            toast: true,
                            position: 'top-end',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    } else {
                        $.fn.dataTable.ext.buttons.excelHtml5.action.call(this, e, dt, button, config);
                    }
                }
            }]
        });
    </script>

</body>

</html>