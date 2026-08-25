<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

require_login_page('../auth/login.php');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My To Do List</title>
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
<body>
<div class="container py-4 py-md-5">
  <div class="row justify-content-center">
    <div class="col-lg-9">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="fw-semibold">
          <i class="ri-user-smile-line"></i>
          Hello, <?= htmlspecialchars($_SESSION['user_name']) ?>
        </span>
        <div class="d-flex align-items-center gap-2">
          <button type="button" id="theme-toggle" class="btn btn-outline-secondary btn-sm theme-toggle-btn" aria-label="Toggle dark mode" title="Toggle dark mode">
            <i class="ri-moon-line" id="theme-toggle-icon"></i>
          </button>
          <a href="../auth/logout.php" class="btn btn-outline-secondary btn-sm">
            <i class="ri-logout-box-line"></i> Logout
          </a>
        </div>
      </div>

      <div class="card todo-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h5 class="mb-0 d-flex align-items-center">
            <i class="ri-checkbox-multiple-line me-2"></i>
            My To Do List
            <span id="pending-badge" class="badge text-bg-primary ms-2">0</span>
          </h5>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" id="btn-mark-all" class="btn btn-sm btn-outline-success rounded-pill">
              <i class="ri-checkbox-circle-line"></i> Mark All
            </button>
            <button type="button" id="btn-unmark-all" class="btn btn-sm btn-outline-secondary rounded-pill">
              <i class="ri-close-circle-line"></i> Unmark All
            </button>
            <button type="button" id="btn-clear-all" class="btn btn-sm btn-outline-danger rounded-pill">
              <i class="ri-delete-bin-2-line"></i> Clear All
            </button>
          </div>
        </div>

        <div class="card-body">
          <form id="add-todo-form" class="row g-2 mb-4" novalidate>
            <div class="col-12 col-sm-7">
              <input type="text" id="todo-text" class="form-control" placeholder="Add a new task..." maxlength="500" required>
            </div>
            <div class="col-6 col-sm-3 d-flex align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="todo-fixed">
                <label class="form-check-label" for="todo-fixed">Fixed</label>
              </div>
            </div>
            <div class="col-6 col-sm-2">
              <button type="submit" class="btn btn-primary w-100">
                <i class="ri-add-line"></i> Add
              </button>
            </div>
          </form>

          <ul class="nav nav-pills mb-3" id="todo-tabs">
            <li class="nav-item">
              <button type="button" class="nav-link active" data-tab="all">All</button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link" data-tab="fixed">Fixed</button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link" data-tab="local">Not Fixed</button>
            </li>
          </ul>

          <ul id="todo-list-page" class="list-unstyled mb-0"></ul>

          <div id="empty-state" class="text-center text-muted py-5 d-none">
            <i class="ri-inbox-line display-4 d-block mb-2"></i>
            <span id="empty-state-text">No tasks yet. Add one above!</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dragula@3.7.3/dist/dragula.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  window.APP_USER_ID = <?= (int)$_SESSION['user_id'] ?>;
</script>
<script src="../assets/js/app.js"></script>
</body>
</html>
