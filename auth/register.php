<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    header('Location: ../pages/todo-list.php');
    exit;
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }

    $name = clean_input($_POST['name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);

            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password) VALUES (:name, :email, :password)'
                );
                $stmt->execute(['name' => $name, 'email' => $email, 'password' => $hash]);

                $_SESSION['registered'] = true;
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register - My To Do List</title>
<script>
(function () {
  var stored = localStorage.getItem('theme');
  var theme = stored === 'light' || stored === 'dark'
    ? stored
    : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.setAttribute('data-bs-theme', theme);
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body d-flex align-items-center">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-11 col-sm-8 col-md-6 col-lg-5">
      <div class="card auth-card">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="auth-logo">
              <i class="ri-checkbox-multiple-line"></i>
            </div>
            <h4 class="mt-2 mb-0">Create your account</h4>
            <p class="text-muted small mb-0">Join the team todo module</p>
          </div>

          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2">
              <ul class="mb-0 ps-3">
                <?php foreach ($errors as $error): ?>
                  <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="mb-3">
              <label for="name" class="form-label">Full name</label>
              <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">Email address</label>
              <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" name="password" minlength="6" required>
            </div>

            <div class="mb-4">
              <label for="confirm_password" class="form-label">Confirm password</label>
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="ri-user-add-line"></i> Register
            </button>
          </form>

          <p class="text-center text-muted small mt-4 mb-0">
            Already have an account? <a href="login.php">Log in</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
