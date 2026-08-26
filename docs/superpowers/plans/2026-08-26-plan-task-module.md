# My Plan / Task Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a second module, "My Plan / Task", that tracks items with Project
Name / Responsible Person / Comment, using the same fixed(DB)/local(browser)
split, tabs, drag-reorder, and bulk actions as the existing To Do List
module — backed by a brand-new `plan_tasks` table that never touches
existing `users`/`todos` data.

**Architecture:** New table + 8 procedural PHP API endpoints under `api/`
(mirroring `todo_*.php` 1:1 in structure: session guard → method guard →
CSRF check → validate → PDO prepared statement → `json_response()`), a new
page `pages/plan-task.php` reusing the existing page shell and CSS classes,
and a new `assets/js/plan.js` adapted from `assets/js/app.js`. A small nav
tab pair is added to both pages so users can switch modules.

**Tech Stack:** Core PHP 8+ (no framework), MySQL via PDO, vanilla JS +
jQuery (loaded but unused, matching existing convention), Bootstrap 5,
Dragula.js, SweetAlert2 — all already in use, no new dependencies.

## Global Constraints

- Only `project_name` is required on add/update; `responsible_person` and
  `comment` are optional (nullable in DB, sent as `null` when empty).
- Every new PHP file must follow the exact guard order used by
  `todo_*.php`: `require_once session.php` → `require_once functions.php`
  → `require_once db.php` (API files only) → `require_login_api()` /
  `require_login_page()` → `require_method()` → `verify_csrf_token()`
  (state-changing endpoints only) → validate → try/catch PDO.
- All new SQL lives in a **new** file `database_plan_tasks.sql`, using only
  `CREATE TABLE IF NOT EXISTS` / `CREATE INDEX` / `INSERT`. It must never
  `ALTER`, `DROP`, or otherwise touch the existing `users` or `todos`
  tables. This file is for the user to import themselves (e.g. drag-and-drop
  into phpMyAdmin) — no task in this plan executes it against a live
  database.
- No git operations (`git add`/`commit`/`push`/`pull`) at any point in this
  plan — the user has explicitly asked that nothing git-related be touched.
  Skip the "Commit" step that would normally end each task.
- Reuse existing CSS classes (`.todo-item`, `.todo-drag-handle`,
  `.todo-text`, `.todo-toggle`, `.todo-edit-btn`, `.todo-delete-btn`,
  `.todo-tab-group`, `.todo-card`) rather than duplicating styles — add new
  CSS only for what's genuinely new (the meta line under each plan item).
- Verify every new/modified PHP file with `php -l` and every new JS file
  with `node --check` — both are available in this environment (PHP 8.4 via
  `php`, Node v24 via `node`). No PHPUnit/test framework exists in this repo
  (confirmed: no `composer.json`, no test directory), so lint checks plus a
  final manual browser walkthrough are the verification strategy, matching
  how the existing To Do module was built and verified.

## File Structure

```
database_plan_tasks.sql          NEW — plan_tasks table + demo seed rows
api/plan_items.php               NEW — GET list
api/plan_store.php               NEW — POST create (fixed item)
api/plan_update.php              NEW — POST update project_name/responsible_person/comment
api/plan_toggle.php              NEW — POST toggle one item's completed state
api/plan_toggle_all.php          NEW — POST mark/unmark all fixed items
api/plan_destroy.php             NEW — POST delete one item
api/plan_clear_all.php           NEW — POST delete all fixed items
api/plan_reorder.php             NEW — POST persist drag order
pages/plan-task.php              NEW — plan/task page (mirrors todo-list.php shell)
pages/todo-list.php              MODIFY — add nav tab pair (To Do List | My Plan / Task)
assets/js/plan.js                NEW — page logic (adapted from assets/js/app.js)
assets/css/style.css             MODIFY — add .todo-item-meta rule
README.md                        MODIFY — document the new module + setup step
```

---

### Task 1: Database table (new, isolated SQL file)

**Files:**
- Create: `database_plan_tasks.sql`

**Interfaces:**
- Produces: table `plan_tasks` with columns `id, user_id, project_name,
  responsible_person, comment, completed, order, is_fixed, created_at,
  updated_at` — every later API task's SQL depends on these exact column
  names.

- [ ] **Step 1: Create the SQL file**

```sql
-- ============================================================
-- My Plan / Task Module - Database Schema + Seed Data
-- Import this file separately from database.sql (e.g. drag-and-
-- drop into phpMyAdmin). It only creates a new `plan_tasks`
-- table and references the existing `users` table by foreign
-- key — it never modifies `users` or `todos`.
-- ============================================================

-- ------------------------------------------------------------
-- Table: plan_tasks
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plan_tasks (
  id                 INT PRIMARY KEY AUTO_INCREMENT,
  user_id            INT NOT NULL,
  project_name       VARCHAR(500) NOT NULL,
  responsible_person VARCHAR(200) NULL,
  comment            VARCHAR(1000) NULL,
  completed          TINYINT(1) NOT NULL DEFAULT 0,
  `order`            INT NOT NULL DEFAULT 0,
  is_fixed           TINYINT(1) NOT NULL DEFAULT 0,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_plan_tasks_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_plan_tasks_user_order ON plan_tasks (user_id, `order`);

-- ------------------------------------------------------------
-- Seed data
-- Assumes the demo user (id 1) from database.sql already exists.
-- Safe to delete these lines if you don't want demo rows.
-- ------------------------------------------------------------
INSERT INTO plan_tasks (user_id, project_name, responsible_person, comment, completed, `order`, is_fixed) VALUES
(1, 'ERP Rollout - Phase 1', 'Alice Rahman', 'Kickoff meeting scheduled', 0, 1, 1),
(1, 'Vendor Contract Review', 'Md. Karim', 'Waiting on legal sign-off', 0, 2, 1),
(1, 'Office Relocation Plan', 'Sara Islam', 'Budget approved', 1, 3, 1);
```

- [ ] **Step 2: Sanity-check the SQL file for destructive statements**

Run (PowerShell):
```
Select-String -Path database_plan_tasks.sql -Pattern '\bDROP\b|\bALTER\b|\bTRUNCATE\b|\bDELETE\b'
```
Expected: no matches (empty output). This confirms the file only creates
new objects and never touches existing data.

---

### Task 2: `plan_items.php` (list) + `plan_store.php` (create)

**Files:**
- Create: `api/plan_items.php`
- Create: `api/plan_store.php`

**Interfaces:**
- Consumes: `plan_tasks` table from Task 1; `json_response()`,
  `verify_csrf_token()`, `clean_input()`, `get_json_body()`,
  `require_method()` from `includes/functions.php`; `require_login_api()`
  from `includes/session.php`; `$pdo` from `config/db.php`.
- Produces: `GET api/plan_items.php` → `{status:"success", data:{items:[...]}}`
  where each item has keys `id, project_name, responsible_person, comment,
  completed, order, is_fixed, created_at, updated_at`. `POST
  api/plan_store.php` with JSON body `{project_name, responsible_person,
  comment}` → `{status:"success", data:{item:{...}}}` (HTTP 201) or
  `{status:"error", message:"..."}` (422 on validation failure). Later JS
  task (Task 5) calls both of these exact URLs and reads these exact keys.

- [ ] **Step 1: Write `api/plan_items.php`**

```php
<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('GET');

try {
    $stmt = $pdo->prepare(
        'SELECT id, project_name, responsible_person, comment, completed, `order`, is_fixed, created_at, updated_at
         FROM plan_tasks
         WHERE user_id = :uid
         ORDER BY `order` ASC, id ASC'
    );
    $stmt->execute(['uid' => $_SESSION['user_id']]);
    $rows = $stmt->fetchAll();

    $items = array_map(static function (array $row): array {
        return [
            'id'                 => (int)$row['id'],
            'project_name'       => $row['project_name'],
            'responsible_person' => $row['responsible_person'],
            'comment'            => $row['comment'],
            'completed'          => (bool)$row['completed'],
            'order'              => (int)$row['order'],
            'is_fixed'           => (bool)$row['is_fixed'],
            'created_at'         => $row['created_at'],
            'updated_at'         => $row['updated_at'],
        ];
    }, $rows);

    json_response('success', ['items' => $items]);
} catch (PDOException $e) {
    json_response('error', 'Could not fetch plan/task items.', 500);
}
```

- [ ] **Step 2: Write `api/plan_store.php`**

```php
<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$projectName = isset($body['project_name']) ? clean_input((string)$body['project_name']) : '';
$responsible = isset($body['responsible_person']) ? clean_input((string)$body['responsible_person']) : '';
$comment = isset($body['comment']) ? clean_input((string)$body['comment']) : '';

if ($projectName === '') {
    json_response('error', 'Project name is required.', 422);
}
if (mb_strlen($projectName) > 500) {
    json_response('error', 'Project name must be 500 characters or fewer.', 422);
}
if (mb_strlen($responsible) > 200) {
    json_response('error', 'Responsible person must be 200 characters or fewer.', 422);
}
if (mb_strlen($comment) > 1000) {
    json_response('error', 'Comment must be 1000 characters or fewer.', 422);
}

try {
    $uid = $_SESSION['user_id'];

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(`order`), 0) + 1 AS next_order FROM plan_tasks WHERE user_id = :uid');
    $stmt->execute(['uid' => $uid]);
    $nextOrder = (int)$stmt->fetch()['next_order'];

    $stmt = $pdo->prepare(
        'INSERT INTO plan_tasks (user_id, project_name, responsible_person, comment, completed, `order`, is_fixed)
         VALUES (:uid, :project_name, :responsible_person, :comment, 0, :order, 1)'
    );
    $stmt->execute([
        'uid'                => $uid,
        'project_name'       => $projectName,
        'responsible_person' => $responsible !== '' ? $responsible : null,
        'comment'            => $comment !== '' ? $comment : null,
        'order'              => $nextOrder,
    ]);
    $id = (int)$pdo->lastInsertId();

    json_response('success', [
        'item' => [
            'id'                 => $id,
            'project_name'       => $projectName,
            'responsible_person' => $responsible !== '' ? $responsible : null,
            'comment'            => $comment !== '' ? $comment : null,
            'completed'          => false,
            'order'              => $nextOrder,
            'is_fixed'           => true,
        ],
    ], 201);
} catch (PDOException $e) {
    json_response('error', 'Could not add item.', 500);
}
```

- [ ] **Step 3: Lint both files**

Run:
```
php -l api/plan_items.php
php -l api/plan_store.php
```
Expected: `No syntax errors detected` for both.

---

### Task 3: `plan_update.php`

**Files:**
- Create: `api/plan_update.php`

**Interfaces:**
- Consumes: same shared includes as Task 2.
- Produces: `POST api/plan_update.php` with JSON body `{id, project_name,
  responsible_person, comment}` → `{status:"success", data:{id,
  project_name, responsible_person, comment}}` or 404/422 error. Task 5's
  edit-modal `preConfirm` handler sends exactly these field names.

- [ ] **Step 1: Write `api/plan_update.php`**

```php
<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$id = isset($body['id']) ? (int)$body['id'] : 0;
$projectName = isset($body['project_name']) ? clean_input((string)$body['project_name']) : '';
$responsible = isset($body['responsible_person']) ? clean_input((string)$body['responsible_person']) : '';
$comment = isset($body['comment']) ? clean_input((string)$body['comment']) : '';

if ($id <= 0) {
    json_response('error', 'Invalid item id.', 422);
}
if ($projectName === '') {
    json_response('error', 'Project name is required.', 422);
}
if (mb_strlen($projectName) > 500) {
    json_response('error', 'Project name must be 500 characters or fewer.', 422);
}
if (mb_strlen($responsible) > 200) {
    json_response('error', 'Responsible person must be 200 characters or fewer.', 422);
}
if (mb_strlen($comment) > 1000) {
    json_response('error', 'Comment must be 1000 characters or fewer.', 422);
}

try {
    $stmt = $pdo->prepare(
        'UPDATE plan_tasks
         SET project_name = :project_name, responsible_person = :responsible_person, comment = :comment
         WHERE id = :id AND user_id = :uid'
    );
    $stmt->execute([
        'project_name'       => $projectName,
        'responsible_person' => $responsible !== '' ? $responsible : null,
        'comment'            => $comment !== '' ? $comment : null,
        'id'                 => $id,
        'uid'                => $_SESSION['user_id'],
    ]);

    if ($stmt->rowCount() === 0) {
        json_response('error', 'Item not found.', 404);
    }

    json_response('success', [
        'id'                 => $id,
        'project_name'       => $projectName,
        'responsible_person' => $responsible !== '' ? $responsible : null,
        'comment'            => $comment !== '' ? $comment : null,
    ]);
} catch (PDOException $e) {
    json_response('error', 'Could not update item.', 500);
}
```

- [ ] **Step 2: Lint the file**

Run: `php -l api/plan_update.php`
Expected: `No syntax errors detected`

---

### Task 4: `plan_toggle.php` + `plan_toggle_all.php`

**Files:**
- Create: `api/plan_toggle.php`
- Create: `api/plan_toggle_all.php`

**Interfaces:**
- Produces: `POST api/plan_toggle.php` body `{id, completed}` →
  `{data:{id, completed}}` or 404/422. `POST api/plan_toggle_all.php` body
  `{completed}` → `{data:{completed}}`. Task 5 calls both with these exact
  bodies.

- [ ] **Step 1: Write `api/plan_toggle.php`**

```php
<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$id = isset($body['id']) ? (int)$body['id'] : 0;
$completed = !empty($body['completed']) ? 1 : 0;

if ($id <= 0) {
    json_response('error', 'Invalid item id.', 422);
}

try {
    $stmt = $pdo->prepare('UPDATE plan_tasks SET completed = :completed WHERE id = :id AND user_id = :uid');
    $stmt->execute(['completed' => $completed, 'id' => $id, 'uid' => $_SESSION['user_id']]);

    if ($stmt->rowCount() === 0) {
        json_response('error', 'Item not found.', 404);
    }

    json_response('success', ['id' => $id, 'completed' => (bool)$completed]);
} catch (PDOException $e) {
    json_response('error', 'Could not update item.', 500);
}
```

- [ ] **Step 2: Write `api/plan_toggle_all.php`**

```php
<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$completed = !empty($body['completed']) ? 1 : 0;

try {
    $stmt = $pdo->prepare('UPDATE plan_tasks SET completed = :completed WHERE user_id = :uid');
    $stmt->execute(['completed' => $completed, 'uid' => $_SESSION['user_id']]);

    json_response('success', ['completed' => (bool)$completed]);
} catch (PDOException $e) {
    json_response('error', 'Could not update items.', 500);
}
```

- [ ] **Step 3: Lint both files**

Run:
```
php -l api/plan_toggle.php
php -l api/plan_toggle_all.php
```
Expected: `No syntax errors detected` for both.

---

### Task 5: `plan_destroy.php` + `plan_clear_all.php`

**Files:**
- Create: `api/plan_destroy.php`
- Create: `api/plan_clear_all.php`

**Interfaces:**
- Produces: `POST api/plan_destroy.php` body `{id}` → `{data:{id}}` or
  404/422. `POST api/plan_clear_all.php` (empty body) → `{data:{deleted:
  true}}`. Task 6 (JS) calls both with these exact bodies.

- [ ] **Step 1: Write `api/plan_destroy.php`**

```php
<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$id = isset($body['id']) ? (int)$body['id'] : 0;

if ($id <= 0) {
    json_response('error', 'Invalid item id.', 422);
}

try {
    $stmt = $pdo->prepare('DELETE FROM plan_tasks WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $_SESSION['user_id']]);

    if ($stmt->rowCount() === 0) {
        json_response('error', 'Item not found.', 404);
    }

    json_response('success', ['id' => $id]);
} catch (PDOException $e) {
    json_response('error', 'Could not delete item.', 500);
}
```

- [ ] **Step 2: Write `api/plan_clear_all.php`**

```php
<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

try {
    $stmt = $pdo->prepare('DELETE FROM plan_tasks WHERE user_id = :uid');
    $stmt->execute(['uid' => $_SESSION['user_id']]);

    json_response('success', ['deleted' => true]);
} catch (PDOException $e) {
    json_response('error', 'Could not clear items.', 500);
}
```

- [ ] **Step 3: Lint both files**

Run:
```
php -l api/plan_destroy.php
php -l api/plan_clear_all.php
```
Expected: `No syntax errors detected` for both.

---

### Task 6: `plan_reorder.php`

**Files:**
- Create: `api/plan_reorder.php`

**Interfaces:**
- Produces: `POST api/plan_reorder.php` body `{order:[id, id, ...]}` (array
  order defines new 1-based position) → `{data:{order:[...]}}` or 422.
  Task 8's `handleReorder()` calls this with the fixed-item ids in their new
  visual order.

- [ ] **Step 1: Write `api/plan_reorder.php`**

```php
<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$order = $body['order'] ?? null;

if (!is_array($order) || empty($order)) {
    json_response('error', 'Invalid order payload.', 422);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('UPDATE plan_tasks SET `order` = :order WHERE id = :id AND user_id = :uid');
    foreach ($order as $index => $id) {
        $stmt->execute([
            'order' => $index + 1,
            'id'    => (int)$id,
            'uid'   => $_SESSION['user_id'],
        ]);
    }

    $pdo->commit();

    json_response('success', ['order' => array_map('intval', $order)]);
} catch (PDOException $e) {
    $pdo->rollBack();
    json_response('error', 'Could not save item order.', 500);
}
```

- [ ] **Step 2: Lint the file**

Run: `php -l api/plan_reorder.php`
Expected: `No syntax errors detected`

---

### Task 7: `pages/plan-task.php` (new page) + nav tabs on `pages/todo-list.php`

**Files:**
- Create: `pages/plan-task.php`
- Modify: `pages/todo-list.php:48-50` (insert nav bar between the "Hello,
  {name}" row and the `.todo-card` div)

**Interfaces:**
- Consumes: `require_login_page()` from `includes/session.php`.
- Produces: element ids `plan-list-page`, `plan-empty-state`,
  `plan-empty-state-text`, `plan-pending-badge`, `add-plan-form`,
  `plan-project-name`, `plan-responsible`, `plan-comment`, `plan-fixed`,
  `plan-btn-mark-all`, `plan-btn-unmark-all`, `plan-btn-clear-all`,
  `plan-tabs`, `plan-tab-all`, `plan-tab-fixed`, `plan-tab-local`, and
  global `window.APP_USER_ID` — Task 8 (`assets/js/plan.js`)
  `document.getElementById`s every one of these exact ids.

- [ ] **Step 1: Create `pages/plan-task.php`**

```php
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
<title>My Plan / Task</title>
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

      <div class="btn-group todo-tab-group mb-3" role="group" aria-label="Switch module">
        <a href="todo-list.php" class="btn btn-outline-primary">To Do List</a>
        <a href="plan-task.php" class="btn btn-primary" aria-current="page">My Plan / Task</a>
      </div>

      <div class="card todo-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h5 class="mb-0 d-flex align-items-center">
            <i class="ri-briefcase-4-line me-2"></i>
            My Plan / Task
            <span id="plan-pending-badge" class="badge text-bg-primary ms-2">0</span>
          </h5>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" id="plan-btn-mark-all" class="btn btn-sm btn-outline-success rounded-pill">
              <i class="ri-checkbox-circle-line"></i> Mark All
            </button>
            <button type="button" id="plan-btn-unmark-all" class="btn btn-sm btn-outline-secondary rounded-pill">
              <i class="ri-close-circle-line"></i> Unmark All
            </button>
            <button type="button" id="plan-btn-clear-all" class="btn btn-sm btn-outline-danger rounded-pill">
              <i class="ri-delete-bin-2-line"></i> Clear All
            </button>
          </div>
        </div>

        <div class="card-body">
          <form id="add-plan-form" class="row g-2 mb-4" novalidate>
            <div class="col-12 col-sm-6">
              <input type="text" id="plan-project-name" class="form-control" placeholder="Project name..." maxlength="500" required>
            </div>
            <div class="col-8 col-sm-4">
              <input type="text" id="plan-responsible" class="form-control" placeholder="Responsible person" maxlength="200">
            </div>
            <div class="col-4 col-sm-2 d-flex align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="plan-fixed">
                <label class="form-check-label" for="plan-fixed">Fixed</label>
              </div>
            </div>
            <div class="col-12 col-sm-10">
              <input type="text" id="plan-comment" class="form-control" placeholder="Comment (optional)" maxlength="1000">
            </div>
            <div class="col-12 col-sm-2">
              <button type="submit" class="btn btn-primary w-100">
                <i class="ri-add-line"></i> Add
              </button>
            </div>
          </form>

          <div class="btn-group todo-tab-group mb-3" role="group" id="plan-tabs" aria-label="Filter items">
            <input type="radio" class="btn-check" name="plan-tab" id="plan-tab-all" data-tab="all" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="plan-tab-all">All</label>

            <input type="radio" class="btn-check" name="plan-tab" id="plan-tab-fixed" data-tab="fixed" autocomplete="off">
            <label class="btn btn-outline-primary" for="plan-tab-fixed">Fixed</label>

            <input type="radio" class="btn-check" name="plan-tab" id="plan-tab-local" data-tab="local" autocomplete="off">
            <label class="btn btn-outline-primary" for="plan-tab-local">Not Fixed</label>
          </div>

          <ul id="plan-list-page" class="list-unstyled mb-0"></ul>

          <div id="plan-empty-state" class="text-center text-muted py-5 d-none">
            <i class="ri-inbox-line display-4 d-block mb-2"></i>
            <span id="plan-empty-state-text">No plan/task items yet. Add one above!</span>
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
<script src="../assets/js/plan.js"></script>
</body>
</html>
```

- [ ] **Step 2: Lint the new page**

Run: `php -l pages/plan-task.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Add the matching nav tab pair to `pages/todo-list.php`**

Find this block (around line 48-50):

```html
      </div>

      <div class="card todo-card">
```

Replace with:

```html
      </div>

      <div class="btn-group todo-tab-group mb-3" role="group" aria-label="Switch module">
        <a href="todo-list.php" class="btn btn-primary" aria-current="page">To Do List</a>
        <a href="plan-task.php" class="btn btn-outline-primary">My Plan / Task</a>
      </div>

      <div class="card todo-card">
```

- [ ] **Step 4: Lint the modified page**

Run: `php -l pages/todo-list.php`
Expected: `No syntax errors detected`

---

### Task 8: `assets/js/plan.js`

**Files:**
- Create: `assets/js/plan.js`

**Interfaces:**
- Consumes: every element id and `window.APP_USER_ID` produced by Task 7;
  every API endpoint and JSON shape produced by Tasks 2–6.
- Produces: the fully working page behavior — nothing later depends on
  this file's internals.

- [ ] **Step 1: Write `assets/js/plan.js`**

```js
(function () {
  'use strict';

  const USER_ID = window.APP_USER_ID;
  const LOCAL_KEY = 'plan_tasks_' + USER_ID;
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  const listEl = document.getElementById('plan-list-page');
  const emptyStateEl = document.getElementById('plan-empty-state');
  const badgeEl = document.getElementById('plan-pending-badge');
  const addForm = document.getElementById('add-plan-form');
  const projectNameInput = document.getElementById('plan-project-name');
  const responsibleInput = document.getElementById('plan-responsible');
  const commentInput = document.getElementById('plan-comment');
  const fixedCheckbox = document.getElementById('plan-fixed');
  const btnMarkAll = document.getElementById('plan-btn-mark-all');
  const btnUnmarkAll = document.getElementById('plan-btn-unmark-all');
  const btnClearAll = document.getElementById('plan-btn-clear-all');
  const tabsEl = document.getElementById('plan-tabs');
  const emptyStateTextEl = document.getElementById('plan-empty-state-text');

  let fixedItems = [];
  let localItems = [];
  let drake = null;
  let activeTab = 'all';

  const EMPTY_STATE_TEXT = {
    all: 'No plan/task items yet. Add one above!',
    fixed: 'No fixed items yet.',
    local: 'No local items yet.',
  };

  // ---------- utilities ----------

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function showError(message) {
    if (window.Swal) {
      Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
      });
    } else {
      alert(message);
    }
  }

  function apiFetch(url, method, body) {
    const opts = {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF_TOKEN,
      },
    };
    if (body !== undefined) {
      opts.body = JSON.stringify(body);
    }

    return fetch(url, opts)
      .then(function (res) {
        return res.json().then(function (json) {
          if (!res.ok || json.status !== 'success') {
            throw new Error(json.message || 'Request failed.');
          }
          return json.data;
        });
      });
  }

  // ---------- theme ----------

  function initTheme() {
    const toggleBtn = document.getElementById('theme-toggle');
    const toggleIcon = document.getElementById('theme-toggle-icon');
    if (!toggleBtn || !toggleIcon) return;

    function applyIcon(theme) {
      toggleIcon.className = theme === 'dark' ? 'ri-sun-line' : 'ri-moon-line';
      toggleBtn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    }

    applyIcon(document.documentElement.getAttribute('data-bs-theme') || 'light');

    toggleBtn.addEventListener('click', function () {
      const current = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
      const next = current === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-bs-theme', next);
      localStorage.setItem('theme', next);
      applyIcon(next);
    });
  }

  // ---------- local storage ----------

  function loadLocalItems() {
    try {
      const raw = localStorage.getItem(LOCAL_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function saveLocalItems() {
    localStorage.setItem(LOCAL_KEY, JSON.stringify(localItems));
  }

  function nextLocalOrder() {
    return localItems.reduce(function (max, t) {
      return Math.max(max, t.order || 0);
    }, 0) + 1;
  }

  // ---------- rendering ----------

  function render() {
    const sortedFixed = fixedItems.slice().sort(function (a, b) { return a.order - b.order; });
    const sortedLocal = localItems.slice().sort(function (a, b) { return a.order - b.order; });

    const showFixed = activeTab === 'all' || activeTab === 'fixed';
    const showLocal = activeTab === 'all' || activeTab === 'local';

    listEl.innerHTML = '';

    if (showFixed) {
      sortedFixed.forEach(function (item) {
        listEl.appendChild(createItemElement(item, 'fixed'));
      });
    }
    if (showLocal) {
      sortedLocal.forEach(function (item) {
        listEl.appendChild(createItemElement(item, 'local'));
      });
    }

    const visible = (showFixed ? sortedFixed : []).concat(showLocal ? sortedLocal : []);
    const total = visible.length;
    const pending = visible.filter(function (t) { return !t.completed; }).length;

    badgeEl.textContent = pending;
    emptyStateTextEl.textContent = EMPTY_STATE_TEXT[activeTab];
    emptyStateEl.classList.toggle('d-none', total > 0);
    listEl.classList.toggle('d-none', total === 0);
  }

  function metaLine(item) {
    const parts = [];
    if (item.responsible_person) {
      parts.push('Responsible: ' + escapeHtml(item.responsible_person));
    }
    if (item.comment) {
      parts.push('Comment: ' + escapeHtml(item.comment));
    }
    if (parts.length === 0) return '';
    return '<div class="todo-item-meta text-muted small ps-4 mt-1">' + parts.join(' &middot; ') + '</div>';
  }

  function createItemElement(item, source) {
    const li = document.createElement('li');
    li.className = 'todo-item d-flex flex-column px-2 py-2 mb-2 fade-in' +
      (item.completed ? ' todo-completed' : '');
    li.dataset.id = item.id;
    li.dataset.source = source;
    li.dataset.completed = item.completed ? 'true' : 'false';

    const pinHtml = source === 'fixed'
      ? '<i class="ri-pushpin-2-fill text-primary" title="Synced item"></i>'
      : '';

    li.innerHTML =
      '<div class="d-flex align-items-center gap-2">' +
        '<span class="todo-drag-handle" title="Drag to reorder"><i class="ri-draggable"></i></span>' +
        '<input type="checkbox" class="form-check-input todo-toggle m-0"' + (item.completed ? ' checked' : '') + '>' +
        pinHtml +
        '<span class="todo-text flex-grow-1' + (item.completed ? ' text-decoration-line-through text-muted' : '') + '">' +
          escapeHtml(item.project_name) +
        '</span>' +
        '<button type="button" class="btn btn-sm btn-link todo-edit-btn" title="Edit"><i class="ri-edit-line"></i></button>' +
        '<button type="button" class="btn btn-sm btn-link todo-delete-btn" title="Delete"><i class="ri-delete-bin-line"></i></button>' +
      '</div>' +
      metaLine(item);

    return li;
  }

  // ---------- add ----------

  addForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const projectName = projectNameInput.value.trim();
    const responsible = responsibleInput.value.trim();
    const comment = commentInput.value.trim();
    if (projectName === '') return;

    if (fixedCheckbox.checked) {
      apiFetch('../api/plan_store.php', 'POST', {
        project_name: projectName,
        responsible_person: responsible,
        comment: comment,
      })
        .then(function (data) {
          fixedItems.push(data.item);
          resetForm();
          render();
        })
        .catch(function () {
          showError('Could not add item.');
        });
    } else {
      localItems.push({
        id: 'local_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
        project_name: projectName,
        responsible_person: responsible || null,
        comment: comment || null,
        completed: false,
        order: nextLocalOrder(),
        is_fixed: false,
      });
      saveLocalItems();
      resetForm();
      render();
    }
  });

  function resetForm() {
    projectNameInput.value = '';
    responsibleInput.value = '';
    commentInput.value = '';
    fixedCheckbox.checked = false;
  }

  // ---------- toggle / edit / delete (event delegation) ----------

  listEl.addEventListener('change', function (e) {
    if (!e.target.classList.contains('todo-toggle')) return;

    const li = e.target.closest('li');
    const id = li.dataset.id;
    const source = li.dataset.source;
    const completed = e.target.checked;

    if (source === 'fixed') {
      const item = fixedItems.find(function (t) { return String(t.id) === String(id); });
      const previous = item.completed;
      item.completed = completed;
      render();

      apiFetch('../api/plan_toggle.php', 'POST', { id: parseInt(id, 10), completed: completed })
        .catch(function () {
          item.completed = previous;
          render();
          showError('Could not update item.');
        });
    } else {
      const item = localItems.find(function (t) { return String(t.id) === String(id); });
      item.completed = completed;
      saveLocalItems();
      render();
    }
  });

  listEl.addEventListener('click', function (e) {
    const editBtn = e.target.closest('.todo-edit-btn');
    const delBtn = e.target.closest('.todo-delete-btn');

    if (editBtn) {
      startEdit(editBtn.closest('li'));
    } else if (delBtn) {
      deleteItem(delBtn.closest('li'));
    }
  });

  function deleteItem(li) {
    const id = li.dataset.id;
    const source = li.dataset.source;

    if (source === 'fixed') {
      apiFetch('../api/plan_destroy.php', 'POST', { id: parseInt(id, 10) })
        .then(function () {
          fixedItems = fixedItems.filter(function (t) { return String(t.id) !== String(id); });
          render();
        })
        .catch(function () {
          showError('Could not delete item.');
        });
    } else {
      localItems = localItems.filter(function (t) { return String(t.id) !== String(id); });
      saveLocalItems();
      render();
    }
  }

  function startEdit(li) {
    const id = li.dataset.id;
    const source = li.dataset.source;
    const collection = source === 'fixed' ? fixedItems : localItems;
    const item = collection.find(function (t) { return String(t.id) === String(id); });
    if (!item || !window.Swal) return;

    Swal.fire({
      title: 'Edit item',
      html:
        '<input id="swal-project-name" class="swal2-input" placeholder="Project name" maxlength="500" value="' + escapeHtml(item.project_name) + '">' +
        '<input id="swal-responsible" class="swal2-input" placeholder="Responsible person" maxlength="200" value="' + escapeHtml(item.responsible_person || '') + '">' +
        '<input id="swal-comment" class="swal2-input" placeholder="Comment" maxlength="1000" value="' + escapeHtml(item.comment || '') + '">',
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Save',
      preConfirm: function () {
        const projectName = document.getElementById('swal-project-name').value.trim();
        if (projectName === '') {
          Swal.showValidationMessage('Project name is required.');
          return false;
        }
        return {
          project_name: projectName,
          responsible_person: document.getElementById('swal-responsible').value.trim(),
          comment: document.getElementById('swal-comment').value.trim(),
        };
      },
    }).then(function (result) {
      if (!result.isConfirmed) return;

      const updated = result.value;

      if (source === 'fixed') {
        apiFetch('../api/plan_update.php', 'POST', {
          id: parseInt(id, 10),
          project_name: updated.project_name,
          responsible_person: updated.responsible_person,
          comment: updated.comment,
        })
          .then(function () {
            item.project_name = updated.project_name;
            item.responsible_person = updated.responsible_person || null;
            item.comment = updated.comment || null;
            render();
          })
          .catch(function () {
            showError('Could not update item.');
          });
      } else {
        item.project_name = updated.project_name;
        item.responsible_person = updated.responsible_person || null;
        item.comment = updated.comment || null;
        saveLocalItems();
        render();
      }
    });
  }

  // ---------- tabs ----------

  tabsEl.addEventListener('change', function (e) {
    if (!e.target.classList.contains('btn-check')) return;

    activeTab = e.target.dataset.tab;
    render();
  });

  // ---------- mark all / unmark all / clear all (scoped to active tab) ----------

  function setAllCompleted(completed) {
    const touchFixed = activeTab === 'all' || activeTab === 'fixed';
    const touchLocal = activeTab === 'all' || activeTab === 'local';

    if (touchFixed) {
      fixedItems.forEach(function (t) { t.completed = completed; });
    }
    if (touchLocal) {
      localItems.forEach(function (t) { t.completed = completed; });
      saveLocalItems();
    }
    render();

    if (touchFixed) {
      apiFetch('../api/plan_toggle_all.php', 'POST', { completed: completed })
        .catch(function () {
          showError('Could not update items.');
        });
    }
  }

  btnMarkAll.addEventListener('click', function () {
    setAllCompleted(true);
  });

  btnUnmarkAll.addEventListener('click', function () {
    setAllCompleted(false);
  });

  btnClearAll.addEventListener('click', function () {
    const touchFixed = activeTab === 'all' || activeTab === 'fixed';
    const touchLocal = activeTab === 'all' || activeTab === 'local';

    const clearLocalState = function () {
      if (touchFixed) fixedItems = [];
      if (touchLocal) {
        localItems = [];
        saveLocalItems();
      }
      render();
    };

    const doClear = function () {
      if (touchFixed) {
        apiFetch('../api/plan_clear_all.php', 'POST', {})
          .then(clearLocalState)
          .catch(function () {
            showError('Could not clear items.');
          });
      } else {
        clearLocalState();
      }
    };

    const confirmText = activeTab === 'fixed'
      ? 'Clear all fixed items?'
      : activeTab === 'local'
        ? 'Clear all local items?'
        : 'Clear all items?';

    if (window.Swal) {
      Swal.fire({
        title: confirmText,
        text: 'This will permanently delete the items in this tab. This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, clear all',
        confirmButtonColor: '#dc3545',
      }).then(function (result) {
        if (result.isConfirmed) doClear();
      });
    } else if (confirm(confirmText + ' This cannot be undone.')) {
      doClear();
    }
  });

  // ---------- drag to reorder ----------

  function initDragula() {
    drake = dragula([listEl], {
      moves: function (el, source, handle) {
        return !!handle.closest('.todo-drag-handle');
      },
    });

    drake.on('drop', function () {
      handleReorder();
    });
  }

  function handleReorder() {
    const items = Array.from(listEl.children);
    const fixedIds = [];
    const localIds = [];

    items.forEach(function (li) {
      if (li.dataset.source === 'fixed') {
        fixedIds.push(li.dataset.id);
      } else {
        localIds.push(li.dataset.id);
      }
    });

    fixedIds.forEach(function (id, index) {
      const item = fixedItems.find(function (t) { return String(t.id) === String(id); });
      if (item) item.order = index + 1;
    });
    localIds.forEach(function (id, index) {
      const item = localItems.find(function (t) { return String(t.id) === String(id); });
      if (item) item.order = index + 1;
    });

    saveLocalItems();
    render();

    if (fixedIds.length > 0) {
      apiFetch('../api/plan_reorder.php', 'POST', { order: fixedIds.map(Number) })
        .catch(function () {
          showError('Could not save item order.');
        });
    }
  }

  // ---------- init ----------

  function init() {
    initTheme();
    localItems = loadLocalItems();
    render();

    apiFetch('../api/plan_items.php', 'GET')
      .then(function (data) {
        fixedItems = data.items;
        render();
      })
      .catch(function () {
        showError('Could not load items.');
      });

    initDragula();
  }

  init();
})();
```

- [ ] **Step 2: Syntax-check the file**

Run: `node --check assets/js/plan.js`
Expected: no output, exit code 0.

---

### Task 9: CSS addition for the meta line

**Files:**
- Modify: `assets/css/style.css` (append after the `.todo-edit-input` rule,
  inside the "Todo page — task list" section, around line 292-295)

**Interfaces:**
- Consumes: nothing new.
- Produces: `.todo-item-meta` class used by Task 8's `metaLine()`.

- [ ] **Step 1: Add the rule**

Find:
```css
.todo-edit-input {
  max-width: 100%;
}
```

Add directly after it:
```css

.todo-item-meta {
  word-break: break-word;
}
```

- [ ] **Step 2: Confirm the rule was added**

Run (PowerShell):
```
Select-String -Path assets/css/style.css -Pattern 'todo-item-meta'
```
Expected: one match.

---

### Task 10: README update

**Files:**
- Modify: `README.md`

**Interfaces:**
- Consumes: nothing (documentation only).
- Produces: nothing consumed by other tasks.

- [ ] **Step 1: Mention the second module in the intro paragraph**

Find:
```markdown
A self-contained PHP + MySQL to-do list app: registration, login, and a
todo list page with fixed (DB-synced) tasks and local (browser-only) tasks,
drag-to-reorder, inline editing, and bulk actions.
```

Replace with:
```markdown
A self-contained PHP + MySQL app with two modules: a To Do List and a My
Plan / Task tracker (project name / responsible person / comment). Both
share the same interaction model — fixed (DB-synced) items and local
(browser-only) items, drag-to-reorder, inline editing, and bulk actions.
```

- [ ] **Step 2: Add a database import step for the new table**

Find:
```markdown
1. **Create the database.**
   Import `database.sql` into MySQL. This creates the `todo_app` database,
   the `users` and `todos` tables, and seeds a demo user with 3 sample tasks.

   ```
   mysql -u root -p < database.sql
   ```
```

Replace with:
```markdown
1. **Create the database.**
   Import `database.sql` into MySQL. This creates the `todo_app` database,
   the `users` and `todos` tables, and seeds a demo user with 3 sample tasks.

   ```
   mysql -u root -p < database.sql
   ```

   Then import `database_plan_tasks.sql` the same way (or drag-and-drop it
   into phpMyAdmin). It only adds the new `plan_tasks` table — it does not
   touch `users` or `todos`.

   ```
   mysql -u root -p < database_plan_tasks.sql
   ```
```

- [ ] **Step 3: Update the file structure diagram**

Find:
```markdown
/todo_app
├── config/db.php              PDO connection
├── auth/{login,register,logout}.php
├── api/                       JSON endpoints (session + CSRF guarded)
├── includes/{session,functions}.php
├── assets/{css/style.css, js/app.js}
├── pages/todo-list.php        main todo page
├── index.php                  redirect to login or todo list
└── database.sql
```

Replace with:
```markdown
/todo_app
├── config/db.php              PDO connection
├── auth/{login,register,logout}.php
├── api/                       JSON endpoints (session + CSRF guarded)
├── includes/{session,functions}.php
├── assets/{css/style.css, js/app.js, js/plan.js}
├── pages/todo-list.php        To Do List page
├── pages/plan-task.php        My Plan / Task page
├── index.php                  redirect to login or todo list
├── database.sql
└── database_plan_tasks.sql    plan_tasks table (import separately)
```

---

### Task 11: Manual functional verification

**Files:** none (verification only)

**Interfaces:** none

This task requires MySQL to be reachable with `database_plan_tasks.sql`
already imported (Task 1 only creates the file — it is not run against any
database automatically, per this plan's constraints). If the executor's
environment cannot reach the configured MySQL server, perform Steps 1-2
only and report that Steps 3+ need to be run by the user in their own
environment.

- [ ] **Step 1: Start the dev server**

Run (from the project root):
```
php -S localhost:8000
```

- [ ] **Step 2: Confirm the app loads**

Open `http://localhost:8000/` in a browser. It should redirect to the login
page (or the To Do list if already logged in via an existing session
cookie).

- [ ] **Step 3: Log in and open both modules**

Log in with the demo account (`demo@example.com` / `password123`). Confirm:
- The To Do List page shows the new "To Do List | My Plan / Task" nav pair,
  with "To Do List" highlighted.
- Clicking "My Plan / Task" navigates to `pages/plan-task.php`, which shows
  the same nav pair with "My Plan / Task" highlighted, and (if the seed
  rows from Task 1 were imported) the 3 seeded items.

- [ ] **Step 4: Exercise the CRUD flow on the new module**

On the My Plan / Task page:
- Add a fixed item (check "Fixed") with a Project Name, Responsible Person,
  and Comment — confirm it appears with the pin icon and the meta line
  ("Responsible: ... · Comment: ...") beneath the project name.
- Add a local item (leave "Fixed" unchecked) — confirm it appears without a
  pin icon, and persists after a page refresh (stored in
  `localStorage['plan_tasks_<uid>']`).
- Tick the checkbox on an item — confirm it strikes through and the pending
  badge count decreases.
- Click Edit on a fixed item — confirm the SweetAlert2 modal opens
  pre-filled with all 3 fields, and Save persists the change after refresh.
- Drag-reorder items using the drag handle — confirm the new order survives
  a page refresh.
- Switch between All / Fixed / Not Fixed tabs — confirm filtering works.
- Use Mark All / Unmark All / Clear All — confirm they only affect the
  active tab's items, matching the To Do list's behavior.

- [ ] **Step 5: Confirm the To Do list is unaffected**

Go back to the To Do List page and confirm existing tasks, tabs, and
behavior are unchanged — this module's edits (Task 7, Step 3) only added
the nav bar.

---

## Self-Review Notes

- **Spec coverage:** SQL table (Task 1) ✓; all 8 API endpoints (Tasks 2-6)
  ✓; add form with the 3 fields (Task 7) ✓; list rendering matching To Do's
  UI (Task 8) ✓; fixed/local split + tabs + drag + bulk actions (Task 8) ✓;
  edit-via-modal (Task 8) ✓; nav tab pair on both pages (Tasks 7-8) ✓;
  isolated SQL file that doesn't touch existing data (Task 1) ✓; no git
  operations (Global Constraints + no Task ends with a commit step) ✓.
- **Placeholder scan:** no TBD/TODO markers; every step has complete code.
- **Type/name consistency:** JSON field names (`project_name`,
  `responsible_person`, `comment`, `id`, `completed`, `order`) match across
  every API task and `plan.js`; element ids match between Task 7's HTML and
  Task 8's `document.getElementById` calls; endpoint URLs match between
  Tasks 2-6 and Task 8's `apiFetch()` calls.
