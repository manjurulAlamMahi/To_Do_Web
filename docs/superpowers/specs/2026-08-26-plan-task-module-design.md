# My Plan / Task Module — Design

Date: 2026-08-26

## Purpose

Add a second module, "My Plan / Task", alongside the existing To Do List module.
It tracks project-style items with a Project Name, Responsible Person, and
Comment, using the same interaction model as the To Do list (fixed/local
split, tabs, drag-reorder, mark all/unmark all/clear all, inline complete
toggle).

The new module must not affect existing To Do data or require any changes to
the `todos`/`users` tables — it lives entirely in a new table imported via a
separate SQL file.

## Data model

New file `database_plan_tasks.sql` (sibling to `database.sql`, imported
independently — e.g. drag-and-drop into phpMyAdmin). Contains only
`CREATE TABLE IF NOT EXISTS` / `CREATE INDEX` statements plus demo seed rows
for the existing demo user. Never modifies `todos` or `users`.

```sql
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
```

Only `project_name` is required (matches the To Do list, where only the task
text is required). `responsible_person` and `comment` are nullable.

## Backend API

New endpoints in `api/`, mirroring the existing `todo_*.php` files 1:1 in
structure (session guard, CSRF check, PDO, `json_response` helper):

| Endpoint | Method | Purpose |
|---|---|---|
| `plan_items.php` | GET | List all plan/task rows for the logged-in user |
| `plan_store.php` | POST | Create a fixed (DB-backed) item |
| `plan_update.php` | POST | Update project_name/responsible_person/comment |
| `plan_toggle.php` | POST | Toggle one item's `completed` state |
| `plan_toggle_all.php` | POST | Mark all / unmark all fixed items |
| `plan_destroy.php` | POST | Delete one item |
| `plan_clear_all.php` | POST | Delete all fixed items |
| `plan_reorder.php` | POST | Persist drag-and-drop order |

Validation mirrors `todo_store.php`/`todo_update.php`: `project_name` required
(≤500 chars), `responsible_person` optional (≤200 chars), `comment` optional
(≤1000 chars). All input passed through `clean_input()`.

## Frontend

- `pages/plan-task.php` — same page shell as `todo-list.php` (theme toggle,
  logout, Bootstrap/dragula/SweetAlert2 includes).
- `assets/js/plan.js` — adapted copy of `app.js`'s logic: fixed vs. local
  split, All/Fixed/Not Fixed tabs, drag-reorder via dragula, mark
  all/unmark all/clear all scoped to the active tab. Uses its own
  localStorage key (`plan_tasks_<uid>`) so it never collides with the To Do
  list's local tasks.
- `assets/css/style.css` — reuse `.todo-item` styling; add a small addition
  for the second (meta) line in each row.

### Navigation

Both `todo-list.php` and `plan-task.php` gain a small nav/tab pair above the
"Hello, {name}" row:

```
[ To Do List ]  [ My Plan / Task ]
```

The current page's tab is visually active/highlighted.

### List item layout

```
[⠿] [✓]  Project Name Here                          [pin] [edit] [del]
         Responsible: John Doe · Comment: some note
```

The second (meta) line renders only when `responsible_person` and/or
`comment` are present; omitted parts (e.g. no comment) are simply left out
of that line.

### Add form

Two rows, since three text fields plus the Fixed checkbox and Add button
don't fit in one row the way the To Do form's single input does:

```
Row 1: [ Project Name (required) ] [ Responsible Person ] [ ] Fixed
Row 2: [ Comment                                        ] [ Add ]
```

### Editing

Because there are three fields instead of one, inline single-span editing
(as used in the To Do list) isn't practical. Edit opens a SweetAlert2 form
modal (SweetAlert2 is already loaded on the page) with all three fields and
Save/Cancel buttons. Both fixed (DB) and local (localStorage) items use the
same modal; fixed items also call `plan_update.php` on save.

## Out of scope

- No changes to the `todos` table, To Do API endpoints, or To Do JS/CSS
  beyond adding the nav tab pair.
- No git operations (commit/push/pull) as part of this work, per explicit
  instruction.
