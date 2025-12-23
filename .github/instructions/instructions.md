# DtecStudio — Copilot Coding Instructions

This file gives concise, actionable instructions for AI coding agents working in this PHP/Bootstrap land management project.

Follow these rules precisely when creating or editing files.

## Quick include snippet (must be at top of every PHP/AJAX file)
Always include these two files at the very top of PHP endpoints and AJAX handlers. Use `include_once` and place them before any output.

```php
<?php
include_once __DIR__ . '/../auth.php';
include_once __DIR__ . '/../db.php';
?>
```

Notes:
- Adjust the relative path if the file lives in a subfolder (for example `admin/` or `ds/`). Use `__DIR__` to build robust includes.

## Project architecture (big picture)
- Procedural PHP (no framework). Entry pages live in the project root and subfolders (`admin/`, `ds/`, `ds/ajax/`).
- Data access is via `db.php` (mysqli). Use prepared statements only.
- Frontend uses Bootstrap 4, jQuery, DataTables, Select2 and Leaflet.js (for the maps).
- Payments/leases are split across `leases`, `lease_payments`, `lease_schedules` tables. Land geometry/coords are in `land_registration`.

## Conventions and patterns (project-specific)
- UI: Always use Bootstrap 4 classes and the existing layout structure (cards, `.card-body`, `.table-responsive`). Match class names used in `admin/index.php` and `ds/index.php` to keep consistent styles.
- Alerts: Use SweetAlert2 `Swal.fire` for all user-facing messages (success, error, confirm). Example:

```js
Swal.fire({ icon: 'success', title: 'Saved', text: 'Lease saved successfully' });
```

- AJAX responses: Every JSON response must be an object with `status` ("success"|"error") and `message`. Include `data` when returning payloads.

Example PHP AJAX reply:

```php
echo json_encode(['status' => 'success', 'message' => 'Saved', 'data' => $payload]);
exit;
```

- SQL: Always use prepared statements with parameter binding. Do not interpolate user input into SQL.

Example prepared statement (mysqli):

```php
$stmt = $con->prepare('SELECT * FROM leases WHERE lease_id = ?');
$stmt->bind_param('i', $lease_id);
$stmt->execute();
$res = $stmt->get_result();
```

- Prevent double submission:
	- Frontend: disable submit button on first click and re-enable on failure.
	- Backend: check for duplicate operations (idempotency) when necessary.

## Important files to inspect when editing features
- `db.php` — database connection (mysqli). See how `$con` is created and reuse it.
- `auth.php` — authentication + permission checks. Include at top of AJAX endpoints.
- `admin/index.php` — main admin dashboard (recently refactored). Good example for panels and Chart.js integration.
- `ds/index.php` — DS (Divisional Secretariat) dashboard with Leaflet map markers.
- `record_payment_simple.php`, `cal_panalty.php` — payment recording and penalty logic; inspect for schedule arithmetic.
- `lease_master.php` and `admin/ajax/save_lease_master.php` (or similar) — example of modal + AJAX + prepared statements.

## Queries, schema assumptions and defensive coding
- The project uses tables like `land_registration`, `leases`, `lease_payments`, `lease_schedules`, `ds_divisions`, `gn_divisions`.
- Queries should be defensive: check `if ($res === false)` and handle null results gracefully. Use `IFNULL()` in SQL when aggregating.

## Charts and visualization
- Use Chart.js (CDN) for charts. Example pattern is in `admin/index.php`.
- For maps use Leaflet.js and pass server data through `json_encode()` into JS variables.

## Debugging tips
- To avoid headers already sent errors: include `auth.php` and `db.php` before any HTML/PHP output.
- If a page dies, check the webserver/PHP error log and run a quick linter: `php -l path/to/file.php`.

## Testing and local run
- This is a XAMPP PHP/MySQL app. Start Apache + MySQL via XAMPP control panel. Access via `http://localhost/land/`.
- Quick local checks:

```powershell
php -l "c:\xampp\htdocs\land\admin\index.php"
```

## Security and data handling
- Never write credentials into code. `db.php` is the single place for DB credentials.
- Escape output with `htmlspecialchars()` when printing user-provided strings into HTML (see tooltips in `ds/index.php`).

## When in doubt — examples
- Adding a new AJAX endpoint: copy pattern from `admin/ajax/save_lease_master.php` (validate POST, include auth/db, prepared stmt, JSON response).
- Adding a new card to `admin/index.php`: follow existing Bootstrap 4 grid usage and add JS initialization after `footer.php`.

---
If anything here is unclear or you want it shortened/further condensed into 20 lines, tell me which sections to prioritize and I'll iterate. 

