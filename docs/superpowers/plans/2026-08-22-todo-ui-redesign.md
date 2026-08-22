# To-Do App UI Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the existing PHP + Bootstrap 5 to-do app (login, register, todo-list pages) into a polished "modern SaaS dashboard" look — indigo accent, Inter typeface, individual task cards, and a light/dark theme toggle — without changing any backend logic, API contracts, or data flow.

**Architecture:** Pure front-end change. Theming rides on Bootstrap 5.3's native `data-bs-theme` color-mode attribute plus targeted overrides of Bootstrap's per-component CSS custom properties (confirmed against the actual shipped `bootstrap@5.3.3/dist/css/bootstrap.css`, not guessed). `assets/css/style.css` is rewritten/extended in three passes (foundation, auth pages, todo page); three PHP pages get small, surgical markup edits; `assets/js/app.js` gets one small additive block for the theme toggle. No PHP business logic, SQL, or JSON API shape changes anywhere.

**Tech Stack:** Core PHP 8 (no framework), Bootstrap 5.3.3 (CDN), Remix Icon (CDN), Dragula.js (CDN), SweetAlert2 (CDN), vanilla JS, Inter (Google Fonts, new).

## Global Constraints

- Pure visual/UI change only. Do not modify `config/db.php`, `database.sql`, `includes/session.php`, `includes/functions.php`, any file under `api/`, or `index.php`.
- Do not change any HTML element `id`, or any class that `assets/js/app.js` selects/binds to: `#todo-list-page`, `#empty-state`, `#pending-badge`, `#add-todo-form`, `#todo-text`, `#todo-fixed`, `#btn-mark-all`, `#btn-unmark-all`, `#btn-clear-all`, `.todo-drag-handle`, `.todo-toggle`, `.todo-text`, `.todo-edit-btn`, `.todo-delete-btn`, `.todo-edit-input`, and the `data-id` / `data-source` / `data-completed` attributes set in `createTaskElement()`. You may add classes alongside these, never rename or remove them.
- No new dependencies, build tools, or package managers. Only the CDN links already in use, plus one new Google Fonts link (Inter). No Composer, no npm, no bundler.
- **This project has no git repository and none should be created as part of this plan** — the user is setting up version control themselves later. Work directly on the files in place; there are no commit steps in this plan.
- This repo has **no automated frontend test suite** (no JS test runner, no CSS linter configured) and, at time of writing, `config/db.php` holds hosting credentials that do not resolve against the local MySQL server on this machine — so pages that require a DB connection (`auth/login.php`, `auth/register.php`) cannot be rendered end-to-end in a local browser right now. Per an explicit decision with the user, **live browser click-through is out of scope for this plan's verification steps.** Each task's "test" step is `php -l` (for touched PHP files) plus grep-based structural sanity checks confirming the expected markup/CSS landed. Task 4 ends with a written manual QA checklist for the user to run themselves once the app is on a reachable database.
- Exact design tokens, color values, and per-page behavior are specified in `docs/superpowers/specs/2026-08-22-todo-ui-redesign-design.md` — this plan implements that spec; consult it for the "why" behind any value used here.

---

## Task 1: Theming foundation (design tokens, Bootstrap re-theme, Inter, dark/light toggle)

**Files:**
- Modify: `assets/css/style.css` (full rewrite)
- Modify: `pages/todo-list.php:9-31` (head + header strip)
- Modify: `auth/login.php:53-60` (head)
- Modify: `auth/register.php:65-72` (head)
- Modify: `assets/js/app.js:46-67`, `assets/js/app.js:408-410` (theme toggle logic + init call)

**Interfaces:**
- Produces: CSS custom properties `--bg`, `--surface`, `--surface-muted`, `--border`, `--text`, `--text-muted`, `--accent`, `--accent-hover`, `--accent-rgb`, `--accent-contrast`, `--shadow-card`, `--shadow-hover` (theme tokens, redefined under `[data-bs-theme="dark"]`) — Tasks 2 and 3 consume these in their own CSS.
- Produces: `<html data-bs-theme="light|dark">` attribute, kept in sync with `localStorage.theme`.
- Produces: `#theme-toggle` button and `#theme-toggle-icon` icon in `pages/todo-list.php`'s header strip; `initTheme()` function in `app.js` that binds to them (no-ops safely if they're absent).
- Consumes: nothing from other tasks (this is the foundation task).

- [ ] **Step 1: Rewrite assets/css/style.css with theme tokens + Bootstrap re-theme**

This replaces the entire current file content.

```css
/* ============================================================
   Design tokens — light theme (default) + dark theme overrides
   ============================================================ */
:root {
  --bg: #f4f5fb;
  --surface: #ffffff;
  --surface-muted: #f8f9fc;
  --border: #e7e9f5;
  --text: #1f2233;
  --text-muted: #6b7094;
  --accent: #6366f1;
  --accent-hover: #4f46e5;
  --accent-rgb: 99, 102, 241;
  --accent-contrast: #ffffff;
  --shadow-card: 0 1px 3px rgba(31, 34, 51, 0.06), 0 1px 2px rgba(31, 34, 51, 0.04);
  --shadow-hover: 0 6px 16px rgba(31, 34, 51, 0.10);
}

[data-bs-theme="dark"] {
  --bg: #14141f;
  --surface: #1b1b29;
  --surface-muted: #1e1e2c;
  --border: #2c2c3d;
  --text: #e7e8f5;
  --text-muted: #8b8ea8;
  --accent: #8183f4;
  --accent-hover: #6366f1;
  --accent-rgb: 129, 131, 244;
  --accent-contrast: #ffffff;
  --shadow-card: 0 1px 3px rgba(0, 0, 0, 0.3), 0 1px 2px rgba(0, 0, 0, 0.2);
  --shadow-hover: 0 6px 20px rgba(0, 0, 0, 0.45);
}

/* ============================================================
   Re-theme Bootstrap's primary color family to the indigo accent,
   and point Bootstrap's body/border/secondary-text tokens at our
   own theme tokens. Each override below is declared ONCE (not
   duplicated per theme): --accent/--bg/--text/etc. are redefined
   per theme scope above, and a var() reference always resolves
   against the current element's cascaded custom-property value —
   not a static snapshot from where it's written — so a single
   declaration here stays correct in both themes.

   Verified against the actual shipped bootstrap@5.3.3/dist/css/
   bootstrap.css: most components read color from their OWN
   per-component custom property (e.g. .btn-primary's --bs-btn-bg
   is a literal hex, not derived from --bs-primary) rather than
   the root --bs-primary, so those components are overridden
   individually below rather than relying on the root override
   alone. .text-primary, .text-bg-primary (the pending badge),
   .link-primary and <a> links DO derive from --bs-primary(-rgb),
   so the root override is sufficient for those.
   ============================================================ */
:root {
  --bs-primary: var(--accent);
  --bs-primary-rgb: var(--accent-rgb);
  --bs-link-color: var(--accent);
  --bs-link-color-rgb: var(--accent-rgb);
  --bs-link-hover-color: var(--accent-hover);
  --bs-body-bg: var(--bg);
  --bs-body-color: var(--text);
  --bs-border-color: var(--border);
  --bs-secondary-color: var(--text-muted);
  --bs-body-font-family: 'Inter', var(--bs-font-sans-serif);
}

.btn-primary {
  --bs-btn-bg: var(--accent);
  --bs-btn-border-color: var(--accent);
  --bs-btn-hover-bg: var(--accent-hover);
  --bs-btn-hover-border-color: var(--accent-hover);
  --bs-btn-active-bg: var(--accent-hover);
  --bs-btn-active-border-color: var(--accent-hover);
  --bs-btn-focus-shadow-rgb: var(--accent-rgb);
}

.btn-outline-primary {
  --bs-btn-color: var(--accent);
  --bs-btn-border-color: var(--accent);
  --bs-btn-hover-bg: var(--accent);
  --bs-btn-hover-border-color: var(--accent);
  --bs-btn-active-bg: var(--accent);
  --bs-btn-active-border-color: var(--accent);
  --bs-btn-focus-shadow-rgb: var(--accent-rgb);
}

.form-control:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 0.25rem rgba(var(--accent-rgb), 0.25);
}

.form-check-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 0.25rem rgba(var(--accent-rgb), 0.25);
}

.form-check-input:checked {
  background-color: var(--accent);
  border-color: var(--accent);
}

/* ============================================================
   Base
   ============================================================ */
body {
  min-height: 100vh;
}

.theme-toggle-btn {
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border-radius: 50%;
}

/* ============================================================
   Dragula drag state
   ============================================================ */
.gu-mirror {
  list-style: none;
  background-color: var(--surface);
  box-shadow: var(--shadow-hover);
  opacity: 0.95;
  border-radius: 12px;
}

.gu-transit {
  opacity: 0.3;
}

/* ============================================================
   Animations
   ============================================================ */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.fade-in {
  animation: fadeIn 0.25s ease;
}
```

- [ ] **Step 2: Add Google Fonts + anti-flash theme script to `pages/todo-list.php`'s `<head>`**

Read the file first to confirm line numbers still match (it should be untouched since the last write):

Old (`pages/todo-list.php:9-17`):
```php
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My To Do List</title>
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
```

New:
```php
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
```

- [ ] **Step 3: Add the theme toggle button to `pages/todo-list.php`'s header strip**

Old (`pages/todo-list.php`, inside the same file, a few lines below Step 2's edit):
```php
      <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="fw-semibold">
          <i class="ri-user-smile-line"></i>
          Hello, <?= htmlspecialchars($_SESSION['user_name']) ?>
        </span>
        <a href="../auth/logout.php" class="btn btn-outline-secondary btn-sm">
          <i class="ri-logout-box-line"></i> Logout
        </a>
      </div>
```

New:
```php
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
```

- [ ] **Step 4: Add the same Google Fonts + anti-flash theme script to `auth/login.php`'s `<head>`**

Old (`auth/login.php:53-60`):
```php
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - My To Do List</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
```

New:
```php
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
```

- [ ] **Step 5: Add the same Google Fonts + anti-flash theme script to `auth/register.php`'s `<head>`**

Old (`auth/register.php:65-72`):
```php
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register - My To Do List</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
```

New:
```php
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
```

- [ ] **Step 6: Add theme-toggle logic to `assets/js/app.js`**

Old (`assets/js/app.js:46-69`, the end of `apiFetch` through the start of the local-storage section):
```js
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

  // ---------- local storage ----------
```

New:
```js
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
```

Then, separately in the same file (`assets/js/app.js:408-411`):

Old:
```js
  function init() {
    localTasks = loadLocalTasks();
    render();
```

New:
```js
  function init() {
    initTheme();
    localTasks = loadLocalTasks();
    render();
```

- [ ] **Step 7: Verify — syntax + structural sanity checks**

Run (PowerShell or the Bash tool; adjust the `php` invocation to whatever resolves on your machine, e.g. `php.bat` if using Laravel Herd on Windows):
```bash
php -l "pages/todo-list.php"
php -l "auth/login.php"
php -l "auth/register.php"
```
Expected: `No syntax errors detected` for all three.

Run:
```bash
grep -c "accent: #6366f1" assets/css/style.css
grep -c "bs-primary: var(--accent)" assets/css/style.css
grep -c "id=\"theme-toggle\"" pages/todo-list.php
grep -c "fonts.googleapis.com" pages/todo-list.php
grep -c "fonts.googleapis.com" auth/login.php
grep -c "fonts.googleapis.com" auth/register.php
grep -c "function initTheme" assets/js/app.js
grep -c "initTheme();" assets/js/app.js
```
Expected: every command prints `1` or higher (nonzero).

Run a brace-balance sanity check on the CSS (there's no CSS linter installed in this project, so this is a lightweight substitute):
```bash
python3 -c "s=open('assets/css/style.css').read(); print('open', s.count('{'), 'close', s.count('}'))"
```
Expected: the two numbers are equal. (If `python3` isn't available, count manually or use any available scripting tool — the point is confirming brace balance, not the specific tool.)

No commit step — this project has no git repository (see Global Constraints).

---

## Task 2: Restyle the auth pages (login + register)

**Files:**
- Modify: `assets/css/style.css` (append auth-page section)
- Modify: `auth/login.php:65-71`
- Modify: `auth/register.php:77-83`

**Interfaces:**
- Consumes: theme tokens from Task 1 (`--bg`, `--surface`, `--shadow-hover`, `--accent`, `--accent-contrast`).
- Produces: `.auth-card`, `.auth-logo` CSS classes for any future auth-related page to reuse.

- [ ] **Step 1: Append auth-page styles to `assets/css/style.css`**

Old (end of file, from Task 1 — the last rule in the file):
```css
.fade-in {
  animation: fadeIn 0.25s ease;
}
```

New:
```css
.fade-in {
  animation: fadeIn 0.25s ease;
}

/* ============================================================
   Auth pages (login / register)
   ============================================================ */
.auth-body {
  background: linear-gradient(160deg, var(--bg) 0%, var(--surface) 100%);
}

.auth-card {
  border: none;
  border-radius: 16px;
  overflow: hidden;
  background-color: var(--surface);
  box-shadow: var(--shadow-hover);
}

.auth-card .alert {
  border: none;
  border-radius: 10px;
}

.auth-logo {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background-color: var(--accent);
  color: var(--accent-contrast);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin: 0 auto 0.75rem;
}
```

- [ ] **Step 2: Restyle the card + add the logo mark in `auth/login.php`**

Old (`auth/login.php:65-71`):
```php
      <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <i class="ri-checkbox-multiple-line display-5 text-primary"></i>
            <h4 class="mt-2 mb-0">Welcome back</h4>
            <p class="text-muted small mb-0">Log in to your todo module</p>
          </div>
```

New:
```php
      <div class="card auth-card">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="auth-logo">
              <i class="ri-checkbox-multiple-line"></i>
            </div>
            <h4 class="mt-2 mb-0">Welcome back</h4>
            <p class="text-muted small mb-0">Log in to your todo module</p>
          </div>
```

- [ ] **Step 3: Restyle the card + add the logo mark in `auth/register.php`**

Old (`auth/register.php:77-83`):
```php
      <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <i class="ri-checkbox-multiple-line display-5 text-primary"></i>
            <h4 class="mt-2 mb-0">Create your account</h4>
            <p class="text-muted small mb-0">Join the team todo module</p>
          </div>
```

New:
```php
      <div class="card auth-card">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <div class="auth-logo">
              <i class="ri-checkbox-multiple-line"></i>
            </div>
            <h4 class="mt-2 mb-0">Create your account</h4>
            <p class="text-muted small mb-0">Join the team todo module</p>
          </div>
```

- [ ] **Step 4: Verify**

```bash
php -l "auth/login.php"
php -l "auth/register.php"
grep -c "auth-card" auth/login.php
grep -c "auth-card" auth/register.php
grep -c "auth-logo" assets/css/style.css
```
Expected: `No syntax errors detected` for both `php -l` calls; every `grep -c` prints a nonzero count.

No commit step — this project has no git repository (see Global Constraints).

---

## Task 3: Restyle the todo page (header strip, toolbar, task cards, empty state)

**Files:**
- Modify: `assets/css/style.css` (append todo-page section)
- Modify: `pages/todo-list.php:33-51`

**Interfaces:**
- Consumes: theme tokens from Task 1 (`--surface`, `--border`, `--surface-muted`, `--text-muted`, `--accent`, `--shadow-card`, `--shadow-hover`).
- Consumes: the exact classes `createTaskElement()` in `app.js` already generates (`.todo-item`, `.todo-completed`, `.todo-drag-handle`, `.todo-toggle`, `.todo-text`, `.todo-edit-btn`, `.todo-delete-btn`) — this task only adds CSS for them, no JS changes.

- [ ] **Step 1: Append todo-page styles to `assets/css/style.css`**

Old (end of file, from Task 2 — the last rule in the file):
```css
.auth-logo {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background-color: var(--accent);
  color: var(--accent-contrast);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin: 0 auto 0.75rem;
}
```

New:
```css
.auth-logo {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background-color: var(--accent);
  color: var(--accent-contrast);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin: 0 auto 0.75rem;
}

/* ============================================================
   Todo page — card + toolbar
   ============================================================ */
.todo-card {
  border: none;
  border-radius: 16px;
  overflow: hidden;
  background-color: var(--surface);
  box-shadow: var(--shadow-card);
}

.todo-card .card-header {
  background-color: var(--surface);
  border-bottom: 1px solid var(--border);
}

.todo-card .card-body {
  background-color: var(--surface);
}

/* ============================================================
   Todo page — task list (individual task cards)
   ============================================================ */
#todo-list-page {
  margin: 0;
  min-height: 40px;
}

.todo-item {
  background-color: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  box-shadow: var(--shadow-card);
  transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

.todo-item:hover {
  box-shadow: var(--shadow-hover);
}

.todo-item.todo-completed {
  background-color: var(--surface-muted);
}

.todo-drag-handle {
  cursor: grab;
  display: inline-flex;
  align-items: center;
  color: var(--text-muted);
  opacity: 0.6;
  transition: opacity 0.15s ease;
}

.todo-item:hover .todo-drag-handle {
  opacity: 1;
}

.todo-drag-handle:active {
  cursor: grabbing;
}

.todo-text {
  word-break: break-word;
}

.todo-item .todo-toggle {
  cursor: pointer;
  flex-shrink: 0;
}

.todo-edit-btn,
.todo-delete-btn {
  border: none;
  background: transparent;
  color: var(--text-muted);
  opacity: 0.55;
  transition: opacity 0.15s ease, color 0.15s ease;
  padding: 0.15rem 0.4rem;
  line-height: 1;
}

.todo-item:hover .todo-edit-btn,
.todo-item:hover .todo-delete-btn,
.todo-edit-btn:focus-visible,
.todo-delete-btn:focus-visible {
  opacity: 1;
}

.todo-edit-btn:hover {
  color: var(--accent);
}

.todo-delete-btn:hover {
  color: #dc3545;
}

.todo-edit-input {
  max-width: 100%;
}
```

- [ ] **Step 2: Restyle the card + toolbar in `pages/todo-list.php`**

Old (`pages/todo-list.php:33-51`):
```php
      <div class="card shadow-sm todo-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h5 class="mb-0 d-flex align-items-center">
            <i class="ri-checkbox-multiple-line me-2"></i>
            My To Do List
            <span id="pending-badge" class="badge text-bg-primary ms-2">0</span>
          </h5>
          <div class="btn-group">
            <button type="button" id="btn-mark-all" class="btn btn-sm btn-outline-success">
              <i class="ri-checkbox-circle-line"></i> Mark All
            </button>
            <button type="button" id="btn-unmark-all" class="btn btn-sm btn-outline-secondary">
              <i class="ri-close-circle-line"></i> Unmark All
            </button>
            <button type="button" id="btn-clear-all" class="btn btn-sm btn-outline-danger">
              <i class="ri-delete-bin-2-line"></i> Clear All
            </button>
          </div>
        </div>
```

New:
```php
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
```

Note: the wrapper changed from `.btn-group` to `.d-flex.flex-wrap.gap-2` deliberately — Bootstrap's `.btn-group` forces flush/shared borders between adjacent buttons (square inner corners), which fights the `rounded-pill` look the design calls for. Plain flex + gap gives independent pill buttons with spacing instead, and `flex-wrap` still keeps them wrapping cleanly on narrow screens (no separate media query needed).

- [ ] **Step 3: Verify**

```bash
php -l "pages/todo-list.php"
grep -c "todo-card" pages/todo-list.php
grep -c "rounded-pill" pages/todo-list.php
grep -c "todo-drag-handle" assets/css/style.css
grep -c "todo-edit-btn" assets/css/style.css
```
Expected: `No syntax errors detected`; every `grep -c` prints a nonzero count.

Confirm no leftover reference to the old wrapper class that could conflict:
```bash
grep -n "btn-group" pages/todo-list.php
```
Expected: no output (the toolbar no longer uses `.btn-group`).

No commit step — this project has no git repository (see Global Constraints).

---

## Task 4: Final verification pass + manual QA handoff

**Files:**
- None modified — this task re-checks everything Tasks 1-3 touched and hands off a manual QA checklist.

**Interfaces:**
- Consumes: the complete state of `assets/css/style.css`, `pages/todo-list.php`, `auth/login.php`, `auth/register.php`, `assets/js/app.js` from Tasks 1-3.

- [ ] **Step 1: Full-project syntax check**

Run:
```bash
php -l config/db.php
php -l index.php
php -l includes/session.php
php -l includes/functions.php
php -l auth/login.php
php -l auth/register.php
php -l auth/logout.php
php -l api/todo_items.php
php -l api/todo_store.php
php -l api/todo_update.php
php -l api/todo_toggle.php
php -l api/todo_toggle_all.php
php -l api/todo_destroy.php
php -l api/todo_reorder.php
php -l api/todo_clear_all.php
php -l pages/todo-list.php
```
Expected: `No syntax errors detected in <path>` for every file, no `Errors parsing` lines. This confirms Tasks 1-3's edits didn't break any file, including ones they didn't intend to touch.

- [ ] **Step 2: Confirm only the expected files changed**

Read each of these five files in full and confirm nothing outside the changes described in Tasks 1-3 is present, and that none of these files were touched: `config/db.php`, `database.sql`, `includes/session.php`, `includes/functions.php`, any file under `api/`, `index.php`. There is no git history to diff against, so this is a direct read-and-compare against the plan's Task 1-3 steps rather than a `git diff`.

- [ ] **Step 3: Confirm preserved JS hooks are all still present**

Run:
```bash
grep -c "id=\"todo-list-page\"" pages/todo-list.php
grep -c "id=\"empty-state\"" pages/todo-list.php
grep -c "id=\"pending-badge\"" pages/todo-list.php
grep -c "id=\"add-todo-form\"" pages/todo-list.php
grep -c "id=\"todo-text\"" pages/todo-list.php
grep -c "id=\"todo-fixed\"" pages/todo-list.php
grep -c "id=\"btn-mark-all\"" pages/todo-list.php
grep -c "id=\"btn-unmark-all\"" pages/todo-list.php
grep -c "id=\"btn-clear-all\"" pages/todo-list.php
```
Expected: every count is exactly `1`. This confirms Task 3's markup edit didn't accidentally rename or drop an ID that `app.js` depends on.

- [ ] **Step 4: Fix anything Steps 1-3 surfaced**

If Steps 1-3 found a syntax error, an out-of-scope change, or a missing ID, fix it directly in the affected file now and re-run the relevant check from Steps 1-3 until it passes. If everything already passed, there is nothing to do here.

- [ ] **Step 5: Hand off manual QA checklist to the user**

Live browser verification was explicitly deferred (per the Global Constraints note — `config/db.php`'s current credentials don't reach a local database from this machine). Before considering this redesign actually *done* in the UX sense, the user should run through this checklist themselves once the app is reachable against a working database (locally with restored local credentials, or after deploying):

1. Register page renders correctly, light and dark (toggle isn't on this page — it inherits whatever `localStorage.theme` already holds from the todo page).
2. Login page renders correctly, light and dark; demo login (`demo@example.com` / `password123`, if `database.sql` was imported) works.
3. Todo page: add a fixed task, add a local task, toggle complete, edit inline (Enter saves / Escape cancels / blur saves), delete, Mark All, Unmark All, Clear All (with confirm dialog), drag-reorder — confirm all still function exactly as before, just look different.
4. Click the theme toggle: confirm it persists across a page reload, and that visiting `auth/login.php` after toggling dark on the todo page shows dark mode there too (shared `localStorage.theme`).
5. Resize to a mobile width (~375px): confirm the header strip, toolbar pills, add-task row, and task cards all remain usable — no horizontal overflow, no button collisions.

This is the last step of the plan — no further code changes are expected unless this checklist surfaces a bug.

---

## Self-Review Notes

- **Spec coverage:** every row of the spec's decision table (style, accent, dark mode, auth layout, task-card style, page chrome, toggle placement, typography, toolbar buttons) maps to a concrete step above. The spec's "Not touched" list (`config/db.php`, `database.sql`, `includes/*.php`, `api/*.php`, `index.php`) is enforced by Task 4 Step 2's direct review.
- **No placeholders:** every step contains complete, final code — no "similar to Task N" references, no TODOs.
- **Type/name consistency:** `initTheme()` is defined once in Task 1 Step 6 and never renamed; `#theme-toggle` / `#theme-toggle-icon` IDs introduced in Task 1 Step 3 are the exact IDs `initTheme()` queries for in Step 6 — checked against each other word-for-word above.
- **No git:** the original draft of this plan included `git init` and per-task commits; those were removed after the user confirmed no git repository should be created for this work. Verification steps were rewritten to not depend on git (no `git diff`, no `git status`, no commit hashes in a progress ledger).
