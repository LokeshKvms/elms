<?php
session_start();
require dirname(__DIR__) . '/config.php';
require INCLUDES_PATH . '/db.php';
require INCLUDES_PATH . '/mail.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['employee_id'] ?? '';
    $name = $_POST['name'];
    $email = $_POST['email'];
    $dept = $_POST['department_id'];
    $pos = $_POST['position'];
    $date = $_POST['hire_date'];

    if ($id) {
        echo "console.log('Editing employee with ID:'', $(this).data('id'));";
        $stmt = $conn->prepare("UPDATE Employees SET name=?, department_id=?, position=?, hire_date=? WHERE employee_id=?");
        $stmt->bind_param("sissi", $name, $dept, $pos, $date, $id);
        $_SESSION['toast'] = ['msg' => 'Employee updated successfully.', 'class' => 'bg-info'];
        $stmt->execute();
        $stmt->close();
    } else {
        $checkStmt = $conn->prepare("SELECT employee_id FROM Employees WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $_SESSION['toast'] = ['msg' => 'Email already exists. Please use a different one.', 'class' => 'bg-danger'];
            $checkStmt->close();
            header("Location: " . BASE_URL . "/admin/manage_employees.php");
            exit;
        }
        $checkStmt->close();

        $password = password_hash('elms@123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO Employees (name, email, department_id, position, hire_date, password, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssisss", $name, $email, $dept, $pos, $date, $password);
        $stmt->execute();
        $newEmpId = $stmt->insert_id;
        $stmt->close();
        $year = date("Y");
        $default_used = 0;
        $leaveTypes = $conn->query("SELECT leave_type_id, type_name FROM leave_types");
        $balanceStmt = $conn->prepare("INSERT INTO leave_balances (employee_id, leave_type_id, year, total_allocated, used) VALUES (?, ?, ?, ?, ?)");

        while ($type = $leaveTypes->fetch_assoc()) {
            $leave_type_id = $type['leave_type_id'];
            $type_name = strtolower($type['type_name']);
            $default_allocated = ($type_name === 'casual leave') ? 12 : 6;
            $balanceStmt->bind_param("iisii", $newEmpId, $leave_type_id, $year, $default_allocated, $default_used);
            $balanceStmt->execute();
        }
        $balanceStmt->close();

        $subject = "Welcome to the Company!";
        $body = "
        <h4>Hi {$name},</h4>
        <p>You have been approved as an employee at our company for the position of {$pos}.</p>
        <p>Your <strong>Email is</strong> {$email} and it's password is elms@123<br>
        <p>Please log in to the portal using your registered email and password. An OTP will be sent to your email for login verification.</p>
        <br>
        <p>Regards,<br>Admin</p>";

        sendMail($email, $subject, $body);

        $_SESSION['toast'] = ['msg' => 'Employee added successfully.', 'class' => 'bg-success'];
    }

    header("Location: " . BASE_URL . "/admin/manage_employees.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM leave_requests WHERE employee_id = $id");
    $conn->query("DELETE FROM leave_balances WHERE employee_id = $id");
    $conn->query("DELETE FROM Employees WHERE employee_id = $id");

    $_SESSION['toast'] = ['msg' => 'Employee deleted.', 'class' => 'bg-danger'];
    header("Location: " . BASE_URL . "/admin/manage_employees.php");
    exit;
}

if (isset($_GET['approve'])) {
    $emp_id = $_GET['approve'];

    $emp = $conn->query("SELECT * FROM Employees WHERE employee_id = $emp_id")->fetch_assoc();
    $conn->query("UPDATE Employees SET status = 'active' WHERE employee_id = $emp_id");

    $subject = "Welcome to the Company!";
    $body = "
    <h4>Hi {$emp['name']},</h4>
    <p>You have been approved as an employee at our company.</p>
    <p><strong>Email:</strong> {$emp['email']}<br>
    <strong>Position:</strong> {$emp['position']}<br>
    <strong>Hire Date:</strong> {$emp['hire_date']}</p>
    <p>Please log in to the portal using your registered email and password. An OTP will be sent to your email for login verification.</p>
    <br><p>Regards,<br>Admin</p>";

    sendMail($emp['email'], $subject, $body);

    $year = date("Y");
    $default_used = 0;
    $leaveTypes = $conn->query("SELECT leave_type_id, type_name FROM leave_types");
    $balanceStmt = $conn->prepare("INSERT INTO leave_balances (employee_id, leave_type_id, year, total_allocated, used) VALUES (?, ?, ?, ?, ?)");

    while ($type = $leaveTypes->fetch_assoc()) {
        $leave_type_id = $type['leave_type_id'];
        $type_name = strtolower($type['type_name']);
        $default_allocated = ($type_name === 'casual leave') ? 12 : 6;
        $balanceStmt->bind_param("iisii", $emp_id, $leave_type_id, $year, $default_allocated, $default_used);
        $balanceStmt->execute();
    }

    $_SESSION['toast'] = ['msg' => 'Employee approved.', 'class' => 'bg-success'];
    header("Location: " . BASE_URL . "/admin/manage_employees.php");
    exit;
}

if (isset($_GET['reject'])) {
    $emp_id = $_GET['reject'];
    $emp = $conn->query("SELECT * FROM Employees WHERE employee_id = $emp_id")->fetch_assoc();
    $conn->query("DELETE FROM Employees WHERE employee_id = $emp_id");

    $subject = "Application Rejected";
    $body = "<h4>Dear {$emp['name']},</h4><p>Your employment application has been rejected. We wish you all the best.</p>";
    sendMail($emp['email'], $subject, $body);

    $_SESSION['toast'] = ['msg' => 'Employee rejected.', 'class' => 'bg-danger'];
    header("Location: " . BASE_URL . "/admin/manage_employees.php");
    exit;
}
include COMMON_PATH . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Approve Users</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    <style>
        .dataTables_filter {
            margin-bottom: 1rem !important;
        }

        #employeeTable thead th {
            text-align: center !important;
        }

        .d-flex.justify-content-between {
            align-items: center;
        }

        .btn {
            margin-bottom: 6px !important;
        }

        .rejectBtn {
            padding-left: 14px !important;
            padding-right: 14px !important;
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">

    <div class="modal fade" id="employeeModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="employeeForm" class="modal-content px-3">
                <div class="modal-header mt-2">
                    <h5 class="modal-title">Add / Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="background-color:white !important;"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="employee_id">

                    <!-- Name Field -->
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>

                    <!-- Email Field -->
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>

                    <!-- Department Field -->
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select name="department_id" id="department_id" class="form-select" required>
                            <option value="" disabled selected>Select Department</option>
                            <?php
                            $deptResult = $conn->query("SELECT department_id, name FROM Departments");
                            while ($dept = $deptResult->fetch_assoc()) {
                                echo "<option value='{$dept['department_id']}'>{$dept['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>


                    <!-- Position Field -->
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" id="position" class="form-control" required>
                    </div>

                    <!-- Hire Date Field -->
                    <div class="mb-3">
                        <label class="form-label">Hire Date</label>
                        <input type="date" name="hire_date" id="hire_date" class="form-control" required>
                    </div>

                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-dark">Save</button>
                </div>
            </form>
        </div>
    </div>


    <main class="flex-grow-1">
        <div class="container mt-1">
            <div class="d-flex mt-3 justify-content-between">
                <h3 class="mb-4 pt-2">List of Employees</h3>
                <button id="addBtn" class="btn btn-dark mb-2" data-bs-toggle="modal" data-bs-target="#employeeModal">Add Employee</button>
            </div>

            <table id="employeeTable" class="table table-bordered my-3">
                <thead class="table-dark text-center">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Hire Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT e.*, d.name AS dept_name FROM Employees e 
                                JOIN Departments d ON e.department_id = d.department_id
                                WHERE e.position!='Manager' 
                                ORDER BY e.status DESC");

                    if ($result->num_rows > 0) {
                        while ($emp = $result->fetch_assoc()) {
                            $isActive = ($emp['status'] === 'active');
                            $approveBtn = $isActive
                                ? "<label class='form-label'>Approved</label>"
                                : "<a href='?approve={$emp['employee_id']}' class='btn btn-success btn-sm mb-2'>Approve</a>";

                            $rejectBtn = $isActive
                                ? ""
                                : "<button class='btn btn-danger btn-sm rejectBtn mb-2' data-id='" . $emp['employee_id'] . "'>Reject</button>";


                            echo "<tr style='text-align:center'>
    <td class='bg-transparent'>{$emp['name']}</td>
    <td class='bg-transparent'>{$emp['email']}</td>
    <td class='bg-transparent'>{$emp['dept_name']}</td>
    <td class='bg-transparent'>{$emp['position']}</td>
    <td class='bg-transparent'>{$emp['hire_date']}</td>
    <td class='bg-transparent'>" .
                                ($isActive ? "<label class='form-label'>Approved</label>" : "<a href='?approve={$emp['employee_id']}' class='btn btn-success btn-sm'>Approve</a>
    <button class='btn btn-danger btn-sm rejectBtn' data-id='{$emp['employee_id']}'>Reject</button>") .
                                "</td>
    <td class='bg-transparent'>
        <button class='btn btn-warning px-3 btn-sm editBtn'
        data-id='{$emp['employee_id']}'
        data-name='{$emp['name']}'
        data-email='{$emp['email']}'
        data-department='{$emp['department_id']}'
        data-position='{$emp['position']}'
        data-date='{$emp['hire_date']}'>
    Edit
</button>

        <button class='btn btn-danger btn-sm deleteBtn' data-id='{$emp['employee_id']}'>Delete</button>
    </td>
</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>No pending approvals</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Toast Notification -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999">
        <div id="toastMsg" class="toast align-items-center text-white bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body" id="toastBody">Action successful.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- Confirm Toast -->
    <div id="confirmToast" class="toast align-items-center text-bg-warning border-0 position-fixed top-0 end-0 m-3" style="z-index:9999" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex justify-content-between">
            <div class="toast-body fw-semibold" id="confirmToastMsg">Are you sure?</div>
            <div class="d-flex align-items-center">
                <a id="confirmToastYesBtn" href="#" class="btn btn-sm btn-light me-2">Yes</a>
                <button type="button" class="btn btn-sm btn-outline-light me-2" data-bs-dismiss="toast">No</button>
            </div>
        </div>
    </div>


    <footer class="text-center mt-auto py-3 text-muted small bottom-0">
        &copy; <?php echo date("Y"); ?> Employee Leave Portal
    </footer>

    <script>
        $(document).ready(function() {
            $('#employeeTable').DataTable({
                lengthChange: false,
                order: [
                    [5, 'asc']
                ],
                pageLength: 5,
                dom: 'Bfrtip', // Enables export buttons
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

            $('#cancelBtn').click(function(e) {
                e.preventDefault();
                $('#employeeModal').modal('hide');
            });

            const toastMessage = '<?= $_SESSION['toast']['msg'] ?? '' ?>';
            const toastClass = '<?= $_SESSION['toast']['class'] ?? '' ?>';

            let confirmActionUrl = '#';

            function showConfirmToast(message, actionUrl) {
                $('#confirmToastMsg').text(message);
                $('#confirmToastYesBtn').attr('href', actionUrl);
                const toast = new bootstrap.Toast(document.getElementById('confirmToast'));
                toast.show();
            }

            // Handle Reject click
            $(document).on('click', '.rejectBtn', function() {
                const empId = $(this).data('id');
                showConfirmToast('Reject and delete this employee?', '?reject=' + empId);
            });

            // Handle Delete click
            $(document).on('click', '.deleteBtn', function() {
                const empId = $(this).data('id');
                showConfirmToast('Delete this employee permanently?', '?delete=' + empId);
            });

            if (toastMessage) {
                $('#toastBody').text(toastMessage);
                $('#toastMsg').removeClass('bg-success bg-info bg-danger')
                    .addClass(toastClass);

                const toast = new bootstrap.Toast(document.getElementById('toastMsg'), {
                    delay: 3000
                });
                toast.show();

                <?php unset($_SESSION['toast']); ?>
            }

            $(document).ready(function() {
                $(document).on('click', '.editBtn', function() {
                    const empId = $(this).data('id');
                    console.log("Editing employee with ID:", empId);

                    if (!empId) {
                        console.error("Employee ID is missing.");
                        return;
                    }

                    $('#employee_id').val(empId);
                    $('#name').val($(this).data('name'));
                    $('#email').val($(this).data('email'));
                    $('#email').attr('readonly', true);
                    $('#department_id').val($(this).data('department'));
                    $('#position').val($(this).data('position'));
                    $('#hire_date').val($(this).data('date'));

                    $('#employeeModal').modal('show');
                });

            });

            $('#addBtn').click(function() {
                $('#email').attr('readonly', false);
                $('#employeeForm')[0].reset();
                $('#employee_id').val('');
                $('#employeeModal').modal('show');
            });

        });
    </script>

</body>

</html>