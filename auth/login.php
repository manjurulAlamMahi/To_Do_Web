<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    header('Location: ../pages/todo-list.php');
    exit;
}

$errors = [];
$email = '';

$justRegistered = !empty($_SESSION['registered']);
unset($_SESSION['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }

    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('SELECT id, name, password FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];
                header('Location: ../pages/todo-list.php');
                exit;
            }

            $errors[] = 'Invalid email or password.';
        } catch (PDOException $e) {
            $errors[] = 'Login failed. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - My To Do List</title>
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
            <h4 class="mt-2 mb-0">Welcome back</h4>
            <p class="text-muted small mb-0">Log in to your todo module</p>
          </div>

          <?php if ($justRegistered): ?>
            <div class="alert alert-success py-2">Account created. You can log in now.</div>
          <?php endif; ?>

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
              <label for="email" class="form-label">Email address</label>
              <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="mb-4">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="ri-login-box-line"></i> Log in
            </button>
          </form>

          <p class="text-center text-muted small mt-4 mb-0">
            Don't have an account? <a href="register.php">Register</a>
          </p>

          <p class="text-center text-muted small mt-2 mb-0">
            Demo account: demo@example.com / password123
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
