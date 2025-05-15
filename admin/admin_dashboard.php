<?php
session_start();

require dirname(__DIR__) . '/config.php';
require INCLUDES_PATH . '/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: " . BASE_URL . "/auth/login.php");
  exit;
}


unset($_SESSION['isOk']);
$resTotal = $conn->query("SELECT COUNT(*) AS total FROM Employees WHERE status='active'");
$totalEmp = $resTotal->fetch_assoc()['total'];

$resEmpApproved = $conn->query("SELECT COUNT(*) AS cnt FROM Employees WHERE status = 'active'");
$approvedEmp = $resEmpApproved->fetch_assoc()['cnt'];

$resEmpPending = $conn->query("SELECT COUNT(*) AS cnt FROM Employees WHERE status = 'inactive'");
$pendingEmp = $resEmpPending->fetch_assoc()['cnt'];

$resTotalLeaves = $conn->query("SELECT COUNT(*) AS cnt FROM Leave_Requests");
$totalLeaves = $resTotalLeaves->fetch_assoc()['cnt'];

$resLeavePending = $conn->query("SELECT COUNT(*) AS cnt FROM Leave_Requests WHERE status = 'pending'");
$pendingLeave = $resLeavePending->fetch_assoc()['cnt'];

$resDepts = $conn->query("SELECT COUNT(*) AS cnt FROM departments");
$deptcnt = $resDepts->fetch_assoc()['cnt'];

$resTotalHolidays = $conn->query("SELECT COUNT(*) AS cnt FROM holidays");
$totalHolidays = $resTotalHolidays->fetch_assoc()['cnt'];

$resPastHolidays = $conn->query("SELECT COUNT(*) AS cnt FROM holidays WHERE holiday_date <= CURDATE()");
$pastHolidays = $resPastHolidays->fetch_assoc()['cnt'];

$resUpcomingHolidays = $conn->query("SELECT COUNT(*) AS cnt FROM holidays WHERE holiday_date > CURDATE()");
$upcomingHolidays = $resUpcomingHolidays->fetch_assoc()['cnt'];

require COMMON_PATH . '/header.php';

?>
<main class="flex-grow-1 container">
  <h2 class="mb-4 pt-4">Admin Dashboard</h2>

  <div class="row row-cols-1 row-cols-md-3 g-4">
    <div class="col">
      <a href=<?= BASE_URL . "/admin/manage_employees.php" ?> class="text-decoration-none">
        <div class="card text-center border-dark border-3">
          <div class="card-body py-3">
            <h6 class="card-title mb-2">Total Employees</h6>
            <p class="display-6 fw-semibold mb-1"><?= $totalEmp ?></p>
            <p class="fw-bold text-primary mb-0">Active</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href=<?= BASE_URL . "/admin/manage_employees.php" ?> class="text-decoration-none">
        <div class="card text-center border-dark border-3 shadow-sm">
          <div class="card-body py-3">
            <h6 class="card-title mb-2">Employees Approved</h6>
            <p class="display-6 fw-semibold mb-1"><?= $approvedEmp ?></p>
            <p class="fw-bold text-success mb-0">Approved</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href=<?= BASE_URL . "/admin/manage_employees.php" ?> class="text-decoration-none">
        <div class="card text-center border-dark border-3 shadow-sm">
          <div class="card-body py-3">
            <h6 class="card-title mb-2">Employees Pending</h6>
            <p class="display-6 fw-semibold mb-1"><?= $pendingEmp ?></p>
            <p class="fw-bold text-warning mb-0">Pending</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href=<?= BASE_URL . "/admin/manage_leaves.php" ?> class="text-decoration-none">
        <div class="card text-center border-dark border-3 shadow-sm">
          <div class="card-body py-3">
            <h6 class="card-title mb-2">Total Leave Requests</h6>
            <p class="display-6 fw-semibold mb-1"><?= $totalLeaves ?></p>
            <p class="fw-bold text-primary mb-0">Submitted</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href=<?= BASE_URL . "/admin/manage_leaves.php" ?> class="text-decoration-none">
        <div class="card text-center border-dark border-3 shadow-sm">
          <div class="card-body py-3">
            <h6 class="card-title mb-2">Pending Leave Approvals</h6>
            <p class="display-6 fw-semibold mb-1"><?= $pendingLeave ?></p>
            <p class="fw-bold text-danger mb-0">Pending</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href=<?= BASE_URL . "/admin/manage_departments.php" ?> class="text-decoration-none">
        <div class="card text-center border-dark border-3 shadow-sm">
          <div class="card-body py-3">
            <h6 class="card-title mb-2">Total Departments</h6>
            <p class="display-6 fw-semibold mb-1"><?= $deptcnt ?></p>
            <p class="fw-bold text-secondary mb-0">Registered</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href=<?= BASE_URL . "/common/holidays.php" ?> class="text-decoration-none">
        <div class="card text-center border-dark border-3 shadow-sm">
          <div class="card-body py-3">
            <h6 class="card-title mb-2">Total Holidays</h6>
            <p class="display-6 fw-semibold mb-1"><?= $totalHolidays ?></p>
            <p class="fw-bold text-primary mb-0">All</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href=<?= BASE_URL . "/common/holidays.php" ?> class="text-decoration-none">
        <div class="card text-center border-dark border-3 shadow-sm">
          <div class="card-body py-3">
            <h6 class="card-title mb-2">Past Holidays</h6>
            <p class="display-6 fw-semibold mb-1"><?= $pastHolidays ?></p>
            <p class="fw-bold text-secondary mb-0">Completed</p>
          </div>
        </div>
      </a>
    </div>

    <div class="col">
      <a href=<?= BASE_URL . "/common/holidays.php" ?> class="text-decoration-none">
        <div class="card text-center border-3 border-dark shadow-sm">
          <div class="card-body py-3">
            <h6 class="card-title mb-2">Upcoming Holidays</h6>
            <p class="display-6 fw-semibold mb-1"><?= $upcomingHolidays ?></p>
            <p class="fw-bold text-info mb-0">Upcoming</p>
          </div>
        </div>
      </a>
    </div>

  </div>
</main>

<footer class="text-center mt-auto py-3 small">
  &copy; <?= date("Y") ?> Employee Leave Portal
</footer>