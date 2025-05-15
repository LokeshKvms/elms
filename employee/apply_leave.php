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
$statusMessage = '';
$redirectTo = '';

$types = $conn->query("SELECT * FROM Leave_Types WHERE leave_type_id IN (1,2,3)");

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $leave_type_id = (int)$_POST['leave_type'];
  $range = explode(' to ', $_POST['leave_range']);
  $start_date = trim($range[0] ?? '');
  $end_date   = isset($range[1]) ? trim($range[1]) : $start_date;
  $reason     = $_POST['reason'];
  $status     = in_array($_POST['action'], ['draft', 'pending']) ? $_POST['action'] : 'draft';

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
    $statusMessage = 'You have selected 0 working days.';
    $redirectTo = BASE_URL . '/employee/user_dashboard.php';
  } elseif ($workingDays > 3) {
    $statusMessage = 'You can only apply for a maximum of 3 working (non-weekend) days.';
    $redirectTo = BASE_URL . '/employee/user_dashboard.php';
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
      $statusMessage = 'No leave balance record found for the selected leave type.';
      $redirectTo = BASE_URL . '/employee/user_dashboard.php';
    } else {
      $balance = $balanceResult->fetch_assoc();
      $remaining = $balance['total_allocated'] - $balance['used'];

      if ($workingDays > $remaining) {
        $statusMessage = "You cannot apply for $workingDays days. Only $remaining day(s) remaining in this leave type.";
        $redirectTo = BASE_URL . '/employee/user_dashboard.php';
      }
    }
  }

  if ($statusMessage === '') {
    $stmt = $conn->prepare("
      INSERT INTO Leave_Requests
        (employee_id, leave_type_id, start_date, end_date, reason, status, requested_at, working_days)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->bind_param("iissssi", $userId, $leave_type_id, $start_date, $end_date, $reason, $status, $workingDays);

    if ($stmt->execute()) {
      if ($status === 'pending') {
        $conn->query("
          UPDATE Leave_Balances 
          SET used = used + $workingDays
          WHERE employee_id = $userId AND leave_type_id = $leave_type_id
        ");
      }
      $statusMessage = $status === 'pending' ? 'Leave submitted successfully.' : 'Leave saved as draft successfully.';
      $redirectTo = $status === 'pending'
        ? BASE_URL . '/employee/user_dashboard.php'
        : BASE_URL . '/employee/drafts.php';
    } else {
      $statusMessage = 'Error: ' . $stmt->error;
      $redirectTo = BASE_URL . '/employee/user_dashboard.php';
    }
  }
}

include COMMON_PATH . '/header.php';

?>
<html>

<head>

  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.tiny.cloud/1/3g4qn6x3hnpmu6lcwk8usodwmm9zjtgi4ppblgvjg2si6egn/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      tinymce.init({
        selector: 'textarea',
        plugins: ['link', 'table', 'emoticons', 'image'],
        toolbar: 'undo redo | bold italic underline | blocks fontfamily fontsize',
        content_css: true,
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
  <style>
    .holiday {
      background-color: #f8d7da !important;
      color: #721c24 !important;
    }
  </style>

</head>

<body>

  <main class="flex-grow-1 container py-4">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8 w-75">
        <div class="card shadow-sm border-1 p-4 px-5 shadow-lg">
          <div class="card-body">
            <h2 class="card-title text-dark mb-3">Apply for Leave</h2>

            <?php if (!empty($statusMessage)): ?>
              <div class="position-fixed top-0 end-0 ps-3 ms-3" style="z-index: 1100;">
                <div class="toast text-white fw-semibold align-items-center bg-success border-0 show" role="alert">
                  <div class="d-flex">
                    <div class="toast-body"><?= htmlspecialchars($statusMessage) ?></div>
                  </div>
                </div>
              </div>
              <script>
                setTimeout(function() {
                  window.location.href = '<?= $redirectTo ?>';
                }, 2000);
              </script>
            <?php endif; ?>

            <form method="post" onsubmit="return syncEditor();">
              <div class="mb-3">
                <label class="form-label">Leave Type</label>
                <select name="leave_type" class="form-select" required>
                  <option value="">-- Select --</option>
                  <?php while ($type = $types->fetch_assoc()): ?>
                    <option value="<?= $type['leave_type_id'] ?>">
                      <?= htmlspecialchars($type['type_name']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Leave Date Range</label>
                <input type="text" name="leave_range" id="leave_range" class="form-control mb-1" required placeholder="Select date range">
                <small id="info-text" class="text-secondary form-text">Note: Max 3 working days (Mon–Fri). Weekends and holidays are excluded.</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Reason</label>
                <textarea id="reason" name="reason" class="form-control mb-4" rows="3"></textarea>
              </div>

              <div class="d-flex justify-content-between">
                <button type="submit" name="action" value="draft" class="btn btn-secondary">Save as Draft</button>
                <button type="submit" name="action" value="pending" class="btn btn-dark">Submit for Approval</button>
              </div>
            </form>
          </div>
        </div>

        <footer class="text-center mt-4 text-muted small">
          &copy; <?= date("Y") ?> Employee Leave Portal
        </footer>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
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
            const day = current.getDay(); // 0 = Sun, 6 = Sat
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
</body>

</html>