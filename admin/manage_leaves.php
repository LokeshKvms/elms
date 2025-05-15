<?php
session_start();
require dirname(__DIR__) . '/config.php';
require INCLUDES_PATH . '/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

$manager_id = $_SESSION['user_id'];

if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $req_id = (int)$_GET['id'];

    $reqRes = $conn->query("SELECT * FROM Leave_Requests WHERE request_id = $req_id");
    if ($reqRes && $reqRes->num_rows === 1) {
        $req = $reqRes->fetch_assoc();
        if ($req['status'] === 'pending') {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $reviewed_at = date("Y-m-d H:i:s");

            $upd = $conn->query(
                "UPDATE Leave_Requests
                 SET status='$status', reviewed_at='$reviewed_at', reviewed_by=$manager_id
                 WHERE request_id=$req_id"
            );

            if ($upd) {
                if ($status === 'rejected') {
                    $restoreDays = (int)$req['working_days'];
                    $conn->query("
                    UPDATE Leave_Balances 
                    SET used = used - $restoreDays 
                    WHERE employee_id = {$req['employee_id']} AND leave_type_id = {$req['leave_type_id']}
                  ");
                    $_SESSION['toast_message'] = ['message' => 'Leave request rejected and balance restored!', 'type' => 'danger'];
                } else {
                    $_SESSION['toast_message'] = ['message' => 'Leave request approved!', 'type' => 'success'];
                }
            } else {
                $_SESSION['toast_message'] = ['message' => 'Failed to update leave request.', 'type' => 'danger'];
            }


            header("Location: manage_leaves.php");
            exit;
        }
    }
}
include COMMON_PATH . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Leave Requests</title>
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
    <style>
        .dataTables_filter {
            margin-bottom: 1rem !important;
        }

        thead th {
            text-align: center !important;
        }
    </style>
</head>

<body>

    <!-- Toast Message -->
    <?php if (isset($_SESSION['toast_message'])): ?>
        <div id="toast" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
            <div class="toast align-items-center text-white bg-<?= $_SESSION['toast_message']['type']; ?> border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <?= $_SESSION['toast_message']['message']; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['toast_message']); ?>
    <?php endif; ?>

    <h3 class="mb-3 pt-4">Pending Leave Requests</h3>

    <?php
    $sql = "
    SELECT r.*, e.name AS emp_name, l.type_name
    FROM Leave_Requests r
    JOIN Employees e ON r.employee_id = e.employee_id
    JOIN Leave_Types l ON r.leave_type_id = l.leave_type_id
    WHERE r.status = 'pending'
";
    $result = $conn->query($sql);
    if (!$result) {
        die("<div class='alert alert-danger'>Query failed: " . $conn->error . "</div>");
    }
    ?>

    <table id="theTable" class="table text-center table-bordered">
        <thead>
            <tr class="table-dark">
                <th>Employee</th>
                <th>Leave Type</th>
                <th>From</th>
                <th>To</th>
                <th>Reason</th>
                <th>Requested At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['emp_name']; ?></td>
                        <td><?= $row['type_name']; ?></td>
                        <td><?= $row['start_date']; ?></td>
                        <td><?= $row['end_date']; ?></td>
                        <td><?= $row['reason']; ?></td>
                        <td><?= $row['requested_at']; ?></td>
                        <td>
                            <a href="manage_leaves.php?action=approve&id=<?= $row['request_id']; ?>" class="btn btn-success btn-sm">Approve</a>
                            <a href="manage_leaves.php?action=reject&id=<?= $row['request_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Reject this leave?');">Reject</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php
    $sqlFetch = "
    SELECT r.*, e.name AS emp_name, l.type_name
    FROM Leave_Requests r
    JOIN Employees e ON r.employee_id = e.employee_id
    JOIN Leave_Types l ON r.leave_type_id = l.leave_type_id
    WHERE r.status != 'draft' AND r.status != 'pending' AND e.employee_id != 1
";
    $result = $conn->query($sqlFetch);
    if (!$result) {
        die("<div class='alert alert-danger'>Query failed: " . $conn->error . "</div>");
    }
    ?>

    <h3 class="mb-3 mt-5">Leave Requests</h3>
    <div class="d-flex justify-content-end align-items-center mb-3">
        <select id="statusFilter" class="form-select form-select-sm" style="width: auto;background-color:#000 !important;color:white !important;">
            <option value="">Filter by Status</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>

    <table id="leaveTable" class="table text-center table-bordered">
        <thead>
            <tr class="table-dark">
                <th>Employee</th>
                <th>Leave Type</th>
                <th>From</th>
                <th>To</th>
                <th>Reason</th>
                <th>Requested At</th>
                <th>Reviewed At</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['emp_name']; ?></td>
                        <td><?= $row['type_name']; ?></td>
                        <td><?= $row['start_date']; ?></td>
                        <td><?= $row['end_date']; ?></td>
                        <td><?= $row['reason']; ?></td>
                        <td><?= date("F j, Y", strtotime($row['requested_at'])) . "<br>" . date("g:i A", strtotime($row['requested_at'])); ?></td>
                        <td><?= date("F j, Y", strtotime($row['reviewed_at'])) . "<br>" . date("g:i A", strtotime($row['reviewed_at'])); ?></td>
                        <td>
                            <?php
                            $status = strtolower($row['status']);
                            $badgeClass = $status === 'approved' ? 'success' : 'danger';
                            echo "<span class='badge bg-{$badgeClass} p-2 text-capitalize'>{$status}</span>";
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan='8' class='text-center'>No leave requests found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <footer class="text-center mt-auto py-3 text-muted small bottom-0">
        &copy; <?= date("Y") ?> Employee Leave Portal
    </footer>

    <script>
        if (!$.fn.dataTable.isDataTable('#theTable')) {
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
                }],
                drawCallback: function() {
                    $('#theTable td').addClass('bg-transparent');
                }
            });
        }

        if (!$.fn.dataTable.isDataTable('#leaveTable')) {
            $('#leaveTable').DataTable({
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
                }],
                drawCallback: function() {
                    $('#leaveTable td').addClass('bg-transparent');
                }
            });
        }

        $('#statusFilter').on('change', function() {
            var selectedStatus = this.value.toLowerCase(); // Get the selected status
            var table = $('#leaveTable').DataTable();
            if (selectedStatus) {
                table.column(7).search(selectedStatus).draw();
            } else {
                table.column(7).search('').draw();
            }
        });

        const toastEl = document.querySelector('.toast');
        if (toastEl) {
            const toast = new bootstrap.Toast(toastEl, {
                delay: 2000
            });
            toast.show();
        }

        document.addEventListener("DOMContentLoaded", function() {
            const toastEl = document.querySelector('.toast');
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl, {
                    delay: 2000
                });
                toast.show();
            }
        });
    </script>
</body>

</html>