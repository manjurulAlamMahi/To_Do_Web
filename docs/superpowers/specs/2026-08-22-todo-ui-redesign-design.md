# To-Do App UI Redesign

Date: 2026-08-22
Status: Approved (via brainstorming session, visual companion mockups)

## Goal

The app's HTML/PHP/JS behavior is done and working (registration, login, todo
CRUD, drag reorder, local vs. fixed tasks). This project is a **visual-only**
redesign: the current pages use plain default Bootstrap styling and the user
wants them to look more polished and user-friendly. No backend logic, API
contracts, database schema, or client-side data flow should change.

## Decisions (from brainstorming)

| Question | Decision |
|---|---|
| Overall style | Modern SaaS dashboard (Linear/Notion-adjacent) |
| Accent color | Indigo/violet, `#6366F1` family |
| Dark mode | Yes — light/dark toggle, not OS-only |
| Auth page layout | Centered card (refined), not split-screen |
| Task list style | Individual rounded task **cards** with spacing, not flat divided rows |
| Page chrome | Minimal header strip (greeting + toggle + logout), not a full navbar |
| Theme toggle placement | Icon button in the header strip (not floating, not OS-only) |
| Typography | Inter via Google Fonts, `system-ui` fallback stack |
| Toolbar buttons (Mark All / Unmark All / Clear All) | Icon + label pill buttons (not icon-only) |

## Design tokens

Implemented as CSS custom properties, light values on `:root`, dark values
under `[data-bs-theme="dark"]` (see Theming mechanism below).

**Light**
- `--bg`: `#f4f5fb` (page background, soft lavender tint)
- `--surface`: `#ffffff` (cards)
- `--surface-muted`: `#f8f9fc` (completed-task background, subtle insets)
- `--border`: `#e7e9f5`
- `--text`: `#1f2233`
- `--text-muted`: `#6b7094`
- `--accent`: `#6366f1`
- `--accent-hover`: `#4f46e5`
- `--accent-contrast`: `#ffffff`

**Dark**
- `--bg`: `#14141f`
- `--surface`: `#1b1b29`
- `--surface-muted`: `#1e1e2c`
- `--border`: `#2c2c3d`
- `--text`: `#e7e8f5`
- `--text-muted`: `#8b8ea8`
- `--accent`: `#8183f4` (lightened for contrast on dark background)
- `--accent-hover`: `#6366f1`

Bootstrap's own `success` (Mark All) and `danger` (Clear All) button colors
are left as Bootstrap defaults in both themes — Bootstrap 5.3's `data-bs-theme`
already adjusts them sensibly. Only the primary/indigo family is overridden.

Radius scale: `10px` (buttons, inputs), `12–14px` (cards). Shadow: a single
soft `box-shadow` token per surface elevation (card vs. hovered task card),
values tuned during implementation to look right in both themes.

## Theming mechanism

- `<html data-bs-theme="light">` by default; JS toggles it to `"dark"` and
  back. This leverages Bootstrap 5.3's native color-mode support for base
  component theming (cards, inputs, badges, borders, muted text, etc. all
  already respond to `data-bs-theme` out of the box).
- Bootstrap ships each component's color as its own hardcoded CSS custom
  property (e.g. `.btn-primary` sets its own `--bs-btn-bg` literal) rather
  than deriving every component from one root `--bs-primary` variable, so a
  single root override is not enough to retheme every indigo-colored
  element. Implementation will override the specific component-scoped
  properties that need to become indigo (primary buttons/outline-buttons,
  the pending-count badge, checked checkboxes, focus rings, links, the pin
  icon) — exact selectors/variable names confirmed by inspecting the shipped
  `bootstrap.min.css` during implementation and verified visually, not
  guessed here.
- Preference persistence: `localStorage.theme` = `"light" | "dark"`. On load,
  an inline `<script>` in `<head>` (before any stylesheet/paint-blocking
  content) reads `localStorage.theme`; if absent, falls back to
  `window.matchMedia('(prefers-color-scheme: dark)')`. This prevents a flash
  of the wrong theme. This inline script is duplicated identically across
  `pages/todo-list.php`, `auth/login.php`, `auth/register.php` (it's ~5
  lines, not worth a shared include for a pre-paint inline script).
- The toggle button (sun/moon icon, swaps based on current theme) lives in
  the minimal header strip on the todo page. Auth pages do not need a
  toggle in the header chrome for this iteration — auth pages inherit
  whatever theme is stored/detected, but there's no toggle control there
  since todo-list.php is the primary surface. (Only add it to auth pages
  too if trivial to reuse — otherwise out of scope.)

## Page-by-page

### Auth pages (`auth/login.php`, `auth/register.php`)

Centered-card layout, refined:
- Page background: soft gradient using `--bg`/`--surface` tones (subtle, not
  a loud gradient).
- One white/surface card, centered, `max-width` ~420px, rounded ~14px,
  soft shadow.
- Small logo mark above the heading: a rounded square/circle in `--accent`
  containing the existing `ri-checkbox-multiple-line` icon (replaces the
  current bare Remix icon).
- Inputs restyled: rounded, neutral border, indigo border+focus-ring on
  focus (via Bootstrap form-control focus override).
- Existing alert (errors/success) styling kept functionally identical, just
  restyled to fit the new palette (softer background tints for
  `alert-danger` / `alert-success`).
- All field `name`/`id` attributes, form `method`/`action`, and the hidden
  CSRF field are unchanged — this is markup/class/CSS only.

### Todo page (`pages/todo-list.php`)

**Header strip** (above the card, not a navbar):
- Left: greeting text ("Hello, {name}"), restyled.
- Right: theme toggle icon button, then Logout button — both small, subtle,
  consistent with the new button styles.

**Card**:
- Header row: title + `ri-checkbox-multiple-line` icon + pending-count badge
  (indigo), then the toolbar: Mark All / Unmark All / Clear All as small
  rounded pill buttons with icon + label, restyled versions of the existing
  outline buttons. **Element IDs (`#btn-mark-all`, `#btn-unmark-all`,
  `#btn-clear-all`, `#pending-badge`) stay exactly as they are** — `app.js`
  binds to these IDs and needs no changes here.
- Add-task row: unchanged fields (`#todo-text`, `#todo-fixed`, submit
  button, form `#add-todo-form`), restyled — rounded input, styled
  checkbox, indigo submit button.
- Task list (`ul#todo-list-page`): each `<li>` becomes its own rounded card
  (not a flat divided row) with spacing between items and a subtle
  hover-lift shadow. Internal structure (drag handle, checkbox, pin icon,
  text span, edit button, delete button) is unchanged — `app.js`'s
  `createTaskElement()` keeps producing the same elements/classes it needs
  for event delegation (`.todo-drag-handle`, `.todo-toggle`, `.todo-text`,
  `.todo-edit-btn`, `.todo-delete-btn`, `data-id`, `data-source`,
  `data-completed`); only the outer `<li>` class list and CSS change to
  produce the card look.
  - Completed tasks: muted text + strikethrough (as today) plus a dimmed
    card background (`--surface-muted`) instead of the current flat
    `todo-completed` background tint.
  - Drag handle: dimmed/low-contrast until hovered.
  - Pin icon: recolored to `--accent` (was Bootstrap's default blue
    `text-primary`).
  - Edit/delete icon buttons: subtle by default, more visible on row hover
    (still always tappable on touch devices — hover is a progressive
    enhancement, not a requirement to reveal them).
- Empty state (`#empty-state`): same icon/copy, restyled to match the new
  muted-text palette.
- Existing `.fade-in` keyframe animation is kept for newly rendered items.

## Implementation scope (files touched)

- `assets/css/style.css` — full rewrite: theme tokens, Bootstrap
  component-color overrides, auth-card styles, header-strip styles,
  toolbar/pill-button styles, task-card styles, empty-state styles,
  animations, responsive rules.
- `pages/todo-list.php` — add Google Fonts `<link>`s, add the inline
  anti-flash-of-wrong-theme `<script>`, add the theme-toggle button markup
  in the header strip, adjust class names on existing elements as needed
  for the new styles (IDs unchanged).
- `auth/login.php`, `auth/register.php` — same Google Fonts links + inline
  theme script, restyled card markup, add the logo mark. Field
  names/ids/form structure unchanged.
- `assets/js/app.js` — **additive only**: a small theme-init block (reads
  `localStorage.theme`, applies `data-bs-theme`) and a click handler for
  the new toggle button, persisting to `localStorage`. No existing
  function, event handler, API call, or data flow is modified.
- **Not touched**: `config/db.php`, `database.sql`, `includes/*.php`,
  `api/*.php`, `index.php`. No PHP logic, API response shape, or DB schema
  changes.

## Testing / verification plan

Since this is a pure front-end restyle:
1. `php -l` on every touched `.php` file (syntax check).
2. Serve the app locally (`php -S localhost:8000` from the project root)
   and click through manually in a browser:
   - Register page renders correctly, light and dark.
   - Login page renders correctly, light and dark; demo login works.
   - Todo page: add a fixed task, add a local task, toggle complete, edit
     inline (Enter/Escape/blur), delete, Mark All, Unmark All, Clear All
     (with confirm), drag-reorder — confirm all still function exactly as
     before, just look different.
   - Toggle dark mode: confirm it persists across a page reload and across
     navigating from login → todo page.
   - Resize to a mobile width: confirm the header strip, toolbar, add-task
     row, and task cards all remain usable (no horizontal overflow, buttons
     don't collide).
3. Since no MySQL credentials with confirmed working access were available
   at build time, live DB testing depends on whatever's configured in
   `config/db.php` at implementation time — call this out explicitly if the
   live click-through can't be completed for that reason, rather than
   claiming success without having run it.

## Out of scope

- Any new features, fields, or API endpoints.
- Changing the SweetAlert2 toast/confirm-dialog default styling.
- A theme toggle on the auth pages (see Theming mechanism note above).
- Any change to `database.sql` or `config/db.php`.
