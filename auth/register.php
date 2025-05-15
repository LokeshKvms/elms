<?php
session_start();
require dirname(__DIR__) . '/config.php';
require INCLUDES_PATH . '/db.php';

if (isset($_SESSION['role'])) {
  if ($_SESSION['role'] === 'admin') {
    header("Location: " . BASE_URL . "/admin/admin_dashboard.php");
    exit;
  } elseif ($_SESSION['role'] === 'employee') {
    header("Location: " . BASE_URL . "/employee/user_dashboard.php");
    exit;
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Register - Leave Portal</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: black;
      backdrop-filter: blur(5px);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    body::-webkit-scrollbar {
      display: none;
    }

    .card {
      max-width: 600px;
      width: 100%;
      border-radius: 1rem;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.4);
      margin: auto;
    }

    .form-text.text-danger {
      font-size: 0.875rem;
    }

    footer {
      text-align: center;
      padding: 1rem 0;
      font-size: 0.875rem;
      color: #6c757d;
    }
  </style>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <script>
    function enableRegister() {
      $("#registerBtn").removeAttr("disabled");

      const recaptchaResponse = grecaptcha.getResponse();
      if (recaptchaResponse.length === 0) {
        console.log('Captcha not completed!');
        $("#registerBtn").attr("disabled", "disabled");
      } else {
        console.log('Captcha verified');
      }
    }
  </script>

  <script>
    function clearForm() {
      document.getElementById("registerForm").reset();
      document.getElementById("registerBtn").disabled = true;
      document.getElementById("passwordMismatch").classList.add("d-none");
    }

    function showToast(message, type) {
      const toastElement = document.createElement('div');
      toastElement.classList.add('toast', 'position-fixed', 'top-0', 'end-0', 'm-3', 'fade', 'show');
      toastElement.classList.add(type === 'success' ? 'bg-success' : 'bg-danger');
      toastElement.innerHTML = `<div class="toast-body text-white">${message}</div>`;
      document.body.appendChild(toastElement);

      setTimeout(() => {
        toastElement.classList.remove('show');
        setTimeout(() => toastElement.remove(), 300);
      }, 3000);
    }

    document.addEventListener("DOMContentLoaded", () => {
      const passwordInput = document.getElementById("password");
      const confirmInput = document.getElementById("confirm_password");
      const registerBtn = document.getElementById("registerBtn");
      const mismatchWarning = document.getElementById("passwordMismatch");

      confirmInput.addEventListener("input", () => {
        if (passwordInput.value && confirmInput.value && passwordInput.value === confirmInput.value) {
          mismatchWarning.classList.add("d-none");
          registerBtn.disabled = false;
        } else {
          mismatchWarning.classList.remove("d-none");
          registerBtn.disabled = true;
        }
      });
    });
  </script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body style="background-color: #F5F7FA;">
  <main class="flex-grow-1 d-flex align-items-center justify-content-center mt-4">
    <div class="card shadow-lg p-5">
      <h3 class="text-center mb-3">Employee Registration</h3>

      <form method="post" id="registerForm">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="name" class="form-label">Full Name</label>
            <input type="text" id="name" name="name" class="form-control" required>
          </div>

          <div class="col-md-6 mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control" required>
          </div>

          <div class="col-md-6 mb-3">
            <label for="position" class="form-label">Position</label>
            <input type="text" id="position" name="position" class="form-control" required>
          </div>

          <div class="col-md-6 mb-3">
            <label for="hire_date" class="form-label">Hire Date</label>
            <input type="date" id="hire_date" name="hire_date" class="form-control" required>
          </div>

          <div class="col-md-12 mb-3">
            <label for="department_id" class="form-label">Department</label>
            <select id="department_id" name="department_id" class="form-select" required>
              <option value="">Select Department</option>
              <?php
              $result = $conn->query("SELECT * FROM Departments");
              while ($row = $result->fetch_assoc()) {
                echo "<option value='{$row['department_id']}'>{$row['name']}</option>";
              }
              ?>
            </select>
          </div>

          <div class="col-md-6 mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-control" required>
          </div>

          <div class="col-md-6 mb-3">
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input type="password" id="confirm_password" class="form-control" required>
            <div class="form-text text-danger d-none" id="passwordMismatch">Passwords do not match</div>
          </div>
          <div class="mb-3 text-center d-flex justify-content-center">
            <div class="g-recaptcha" data-sitekey="6LeM1DYrAAAAADr-eWGoIv3aQBXt13clCX_mnD_H" data-callback="enableRegister"></div>
          </div>

        </div>

        <div class="d-flex justify-content-between">
          <button name="register" class="btn btn-dark w-50 me-2" id="registerBtn" disabled>Register</button>
          <button type="button" onclick="clearForm()" class="btn btn-outline-dark w-50 ms-2">Clear</button>
        </div>
      </form>

      <p class="mt-3 text-center">
        Already have an account? <a href="login.php" class="text-primary">Login here</a>
      </p>

      <?php
      if (isset($_POST['register'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $position = $_POST['position'];
        $hire_date = $_POST['hire_date'];
        $department_id = $_POST['department_id'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $recaptchaSecret = '6LeM1DYrAAAAAHRYy5S_x8rjEwy6RneNfYr3DHsM';
        $recaptchaResponse = $_POST['g-recaptcha-response'];

        $verifyUrl = "https://www.google.com/recaptcha/api/siteverify?secret=$recaptchaSecret&response=$recaptchaResponse";
        $verifyResponse = file_get_contents($verifyUrl);
        $responseData = json_decode($verifyResponse);

        if (!$responseData->success) {
          echo "<script>showToast('Please complete the CAPTCHA.', 'danger');</script>";
          exit;
        }
        $checkStmt = $conn->prepare("SELECT employee_id FROM Employees WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
          echo "<script>showToast('Email already exists', 'danger');</script>";
          $checkStmt->close();
        } else {

          $checkStmt->close();

          $stmt = $conn->prepare("INSERT INTO Employees (name, email, department_id, position, hire_date, status, password) VALUES (?, ?, ?, ?, ?, 'inactive', ?)");
          $stmt->bind_param("ssisss", $name, $email, $department_id, $position, $hire_date, $password);

          if ($stmt->execute()) {
            echo "<script>
            showToast('Registration successful. Awaiting manager approval.', 'success');
            setTimeout(() => { window.location.href = 'login.php'; }, 2000);
            </script>";
          } else {
            echo "<script>showToast('Error: " . $stmt->error . "', 'danger');</script>";
          }
        }
      }
      ?>
    </div>
  </main>

  <footer>
    &copy; <?= date("Y") ?> Employee Leave Portal
  </footer>
</body>

</html>