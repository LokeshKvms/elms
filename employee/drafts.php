<!DOCTYPE html>
<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
  header("Location: " . BASE_URL . "/auth/login.php");
  exit;
}
require_once dirname(__DIR__) . '/config.php';
require INCLUDES_PATH . '/db.php';

$userId = $_SESSION['user_id'];
$message = '';
$redirectTo = '';

if (isset($_GET['delete'])) {
  $delId = (int)$_GET['delete'];
  $stmt = $conn->prepare("DELETE FROM Leave_Requests WHERE request_id = ? AND employee_id = ? AND status = 'draft'");
  $stmt->bind_param("ii", $delId, $userId);
  $stmt->execute();
  header("Location: drafts.php");
  exit;
}

$holidays = [];
$holidayQuery = "SELECT holiday_date FROM holidays";
$holidayResult = $conn->query($holidayQuery);

if ($holidayResult) {
  while ($row = $holidayResult->fetch_assoc()) {
    $holidays[] = $row['holiday_date'];
  }
}

function countWeekdays($start, $end)
{
  $start = new DateTime($start);
  $end = new DateTime($end);
  $count = 0;
  while ($start <= $end) {
    if (!in_array($start->format('N'), [6, 7])) {
      $count++;
    }
    $start->modify('+1 day');
  }
  return $count;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
  $reqId = (int)$_POST['request_id'];
  $leave_type_id = (int)$_POST['leave_type'];

  if (!empty($_POST['leave_range'])) {
    $range = explode('to', $_POST['leave_range']);
    $start_date = trim($range[0] ?? '');
    $end_date = isset($range[1]) ? trim($range[1]) : $start_date;
  } else {
    $start_date = $end_date = '';
  }

  $reason = $_POST['reason'];
  $status = in_array($_POST['action'], ['draft', 'pending']) ? $_POST['action'] : 'draft';

  $workingDays = 0;
  $current = new DateTime($start_date);
  $endObj = new DateTime($end_date);

  while ($current <= $endObj) {
    $day = $current->format('N');
    $dateStr = $current->format('Y-m-d');
    if ($day < 6 && !in_array($dateStr, $holidays)) {
      $workingDays++;
    }
    $current->modify('+1 day');
  }

  if ($workingDays == 0) {
    $message = 'You have selected 0 working days.';
    $redirectTo = 'drafts.php';
  } elseif ($workingDays > 3) {
    $message = 'You can only apply for a maximum of 3 working days.';
    $redirectTo = 'drafts.php';
  } elseif ($status === 'pending') {
    $year = date('Y');
    $balanceQuery = $conn->prepare("
      SELECT total_allocated, used 
      FROM Leave_Balances 
      WHERE employee_id = ? AND leave_type_id = ? AND year = ?
    ");
    $balanceQuery->bind_param("iii", $userId, $leave_type_id, $year);
    $balanceQuery->execute();
    $balanceResult = $balanceQuery->get_result();

    if ($balanceResult->num_rows === 0) {
      $message = 'No leave balance record found for the selected leave type.';
      $redirectTo = 'drafts.php';
    } else {
      $balance = $balanceResult->fetch_assoc();
      $remaining = $balance['total_allocated'] - $balance['used'];

      if ($workingDays > $remaining) {
        $message = "You cannot apply for $workingDays days. Only $remaining day(s) remaining in this leave type.";
        $redirectTo = 'drafts.php';
      }
    }
  }

  if (empty($message)) {
    $upd = $conn->prepare("UPDATE Leave_Requests SET leave_type_id = ?, start_date = ?, end_date = ?, reason = ?, status = ? WHERE request_id = ? AND employee_id = ?");
    $upd->bind_param("issssii", $leave_type_id, $start_date, $end_date, $reason, $status, $reqId, $userId);
    $upd->execute();

    $message = "Draft " . ($status === 'pending' ? "submitted" : "updated") . " successfully.";

    if ($status === 'pending') {
      $conn->query("
        UPDATE Leave_Balances 
        SET used = used + $workingDays 
        WHERE employee_id = $userId AND leave_type_id = $leave_type_id
      ");
    }

    $redirectTo = ($status === 'pending') ?  BASE_URL . '/employee/user_dashboard.php' :BASE_URL . '/employee/drafts.php';
  }
}

$editing = false;
if (isset($_GET['edit'])) {
  $editId = (int)$_GET['edit'];
  $res = $conn->prepare("SELECT * FROM Leave_Requests WHERE request_id = ? AND employee_id = ? AND status = 'draft'");
  $res->bind_param("ii", $editId, $userId);
  $res->execute();
  $draft = $res->get_result()->fetch_assoc();
  if ($draft) {
    $editing = true;
  }
}

$types = $conn->query("SELECT * FROM Leave_Types WHERE leave_type_id IN (1, 2, 3)");
$drafts = $conn->prepare("SELECT r.request_id, l.type_name, r.start_date, r.end_date, r.reason FROM Leave_Requests r JOIN Leave_Types l ON r.leave_type_id = l.leave_type_id WHERE r.employee_id = ? AND r.status = 'draft' ORDER BY r.requested_at DESC");
$drafts->bind_param("i", $userId);
$drafts->execute();
$draftList = $drafts->get_result();

include COMMON_PATH . '/header.php';
?>

<head>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.tiny.cloud/1/3g4qn6x3hnpmu6lcwk8usodwmm9zjtgi4ppblgvjg2si6egn/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>

  <style>
    .holiday {
      background-color: #f8d7da !important;
      color: #721c24 !important;
    }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      tinymce.init({
        selector: 'textarea',
        plugins: ['link', 'table', 'emoticons', 'image'],
        toolbar: 'undo redo | bold italic underline | blocks fontfamily fontsize',
        content_css: true, // Disable default styles
        height: 300,
        menubar: true,
        setup: function(editor) {
          editor.on('change', function() {
            editor.save();
          });
        }
      });
    });

    function syncEditor() {
      tinymce.triggerSave();
      const content = document.getElementById("reason").value.trim();
      if (content === "") {
        alert("Please enter a reason for your leave.");
        return false;
      }
      const range = document.getElementById("leave_range").value.trim();
      const dates = range.split(" to ");
      if (dates.length < 1 || !dates[0]) {
        alert("Please select a valid date range.");
        return false;
      }
      return true;
    }
  </script>

</head>

<body>

  <main class="flex-grow-1 container py-4">
    <h2 class="mb-4">My Drafts</h2>
    <?= $message ?>

    <?php if (!$editing): ?>
      <?php if ($draftList->num_rows): ?>
        <table id="theTable" class="table text-center table-bordered">
          <thead class="table-dark">
            <tr>
              <th>S.No</th>
              <th>Type</th>
              <th>From</th>
              <th>To</th>
              <th>Reason</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1;
            while ($d = $draftList->fetch_assoc()): ?>
              <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars($d['type_name']) ?></td>
                <td><?= $d['start_date'] ?></td>
                <td><?= $d['end_date'] ?></td>
                <td><?= $d['reason'] ?></td>
                <td>
                  <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="?edit=<?= $d['request_id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="?delete=<?= $d['request_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this draft?');">Delete</a>
                  </div>
                </td>

              </tr>
            <?php $i++;
            endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="alert alert-light">No drafts found.</div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($editing): ?>
      <div class="card p-4 px-5">
        <h4 class="my-3">Edit Draft</h4>
        <form method="post">
          <input type="hidden" name="request_id" value="<?= $draft['request_id'] ?>">
          <div class="mb-3">
            <label class="form-label">Leave Type</label>
            <select name="leave_type" class="form-select" required>
              <?php while ($t = $types->fetch_assoc()): ?>
                <option value="<?= $t['leave_type_id'] ?>" <?= $t['leave_type_id'] == $draft['leave_type_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t['type_name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Leave Date Range</label>
            <input type="text" name="leave_range" id="leave_range" class="form-control" required placeholder="Select date range" value="<?= htmlspecialchars($draft['start_date'] . ($draft['end_date'] !== $draft['start_date'] ? ' to ' . $draft['end_date'] : '')) ?>">
            <small id="info-text" class="text-muted form-text">Note: Max 3 working days (Mon–Fri). Weekends and holidays are excluded automatically.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <textarea name="reason" class="form-control" rows="3"><?= htmlspecialchars($draft['reason']) ?></textarea>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <div class="d-flex justify-content-start gap-1">
              <button type="submit" name="action" value="draft" class="btn btn-secondary me-2">Save as Draft</button>
              <a href="?delete=<?= $draft['request_id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this draft permanently?');">Delete Draft</a>
            </div>
            <button type="submit" name="action" value="pending" class="btn btn-dark">Submit for Approval</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <footer class="text-center mt-auto py-3 text-muted small bottom-0">
      &copy; <?= date("Y") ?> Employee Leave Portal
    </footer>
  </main>

  <script>
    $('td').addClass('bg-transparent');
    $('th').addClass('text-center');
    $(document).ready(function() {
      $('#theTable').DataTable({
        lengthChange: false,
        dom: 'Bfrtip',
        buttons: [{
          extend: 'excel',
          text: 'Export to Excel'
        }]
      });
    });

    const holidays = <?= json_encode($holidays) ?>;

    flatpickr("#leave_range", {
      mode: "range",
      dateFormat: "Y-m-d",
      minDate: "today",
      maxDate: "2025-12-31",

      onDayCreate: function(dObj, dStr, fp, dayElem) {
        const date = dayElem.dateObj.toISOString().split('T')[0];
        if (holidays.includes(date)) {
          dayElem.classList.add('holiday');
          dayElem.title = "Holiday";
        }
      },

      onChange: function(selectedDates, dateStr, instance) {
        if (selectedDates.length === 2) {
          const start = selectedDates[0];
          const end = selectedDates[1];

          let count = 0;
          const current = new Date(start);

          while (current <= end) {
            const day = current.getDay();
            const dateStr = current.toISOString().split('T')[0];
            if (day !== 0 && day !== 6 && !holidays.includes(dateStr)) {
              count++;
            }
            current.setDate(current.getDate() + 1);
          }

          if (count == 0) {
            alert("You have selected 0 working days.");
            instance.clear();
            document.getElementById('info-text').innerHTML = `Note: Max 3 working days (Mon–Fri). Weekends and holidays are excluded.`;
          }

          if (count > 3) {
            alert("You can only apply for a maximum of 3 working days excluding weekends and holidays.");
            instance.clear();
            document.getElementById('info-text').innerHTML = `Note: Max 3 working days (Mon–Fri). Weekends and holidays are excluded.`;

          }
          document.getElementById('info-text').innerHTML = `No. of days leaves applied : ${count}`;
        }
      }
    });
  </script>

  <?php if ($message): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
      <div class="toast align-items-center bg-success text-white border-0 show" role="alert">
        <div class="d-flex">
          <div class="toast-body"><?= htmlspecialchars($message) ?></div>
        </div>
      </div>
    </div>

    <script>
      setTimeout(function() {
        window.location.href = '<?= $redirectTo ?>';
      }, 3000);
    </script>
  <?php endif; ?>
</body>

</html>