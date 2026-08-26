# My To Do List (Team ERP Todo Module)

A self-contained PHP + MySQL app with two modules: a To Do List and a My
Plan / Task tracker (project name / responsible person / comment). Both
share the same interaction model — fixed (DB-synced) items and local
(browser-only) items, drag-to-reorder, inline editing, and bulk actions.

## Tech stack

- Core PHP 8+ (no framework, no Composer)
- MySQL via PDO with prepared statements
- Bootstrap 5, jQuery, vanilla JS
- Remix Icon (CDN)
- Dragula.js for drag-to-reorder (CDN)
- SweetAlert2 for toasts/confirm dialogs (CDN)

## Setup

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

2. **Configure the database connection.**
   Edit [config/db.php](config/db.php) and set your MySQL host, username,
   and password if they differ from the defaults (`127.0.0.1` / `root` / no
   password).

3. **Serve the app.**
   Point your web server (Apache, Nginx, or PHP's built-in server) at the
   project root. For a quick local check with the built-in server:

   ```
   php -S localhost:8000
   ```

   Then open `http://localhost:8000/` in your browser. `index.php` redirects
   to the login page automatically.

4. **Log in.**
   Use the seeded demo account, or register a new one:

   - Email: `demo@example.com`
   - Password: `password123`

## How fixed vs. local tasks work

- **Fixed tasks** (checkbox ticked when adding) are stored in the `todos`
  table in MySQL and synced across devices/browsers for that account. All
  CRUD operations go through the `/api` endpoints.
- **Local tasks** (checkbox unticked) live only in the browser's
  `localStorage`, under the key `tasks_{user_id}`. They never touch the
  server and are private to that browser.
- Fixed tasks always render above local tasks. Dragging can reorder within
  each group; the fixed group is always kept on top.

## File structure

```
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

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt) and verified with
  `password_verify()`.
- All SQL uses PDO prepared statements.
- Every state-changing API call validates a per-session CSRF token sent via
  the `X-CSRF-Token` header.
- All todo queries are scoped to `$_SESSION['user_id']`, so one user cannot
  read or modify another user's tasks.
