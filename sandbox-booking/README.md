# Malware Analysis Sandbox Booking

A small Laravel application for analysts to view available malware-analysis sandboxes, book time, and cancel bookings.

## Stack

- PHP 8.2+
- Laravel 12
- SQLite for local development and tests; MySQL 8 is recommended for deployments that need concurrent booking protection
- Blade, with a small browser-side script for the day view

## Run locally

From the project directory:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Open `http://127.0.0.1:8000`. The database seeder creates five example sandboxes. The default SQLite database file is `database/database.sqlite`.

## Tests

```powershell
php artisan test
```

The feature tests cover the API, UTC date filtering, adjacent and overlapping bookings, the two-hour limit, the maintenance window, cancellation, and offset-to-UTC normalization.

## API

All endpoints are under `/api`. Booking datetimes use ISO 8601; timezone offsets are accepted and normalized to UTC.

| Method | URI | Description |
|---|---|---|
| GET | `/api/sandboxes` | List sandboxes |
| GET | `/api/bookings?date=YYYY-MM-DD` | List bookings that overlap the UTC day; omitting `date` uses today's UTC date |
| POST | `/api/bookings` | Create a booking |
| DELETE | `/api/bookings/{id}` | Cancel a booking |

Create-booking JSON:

```json
{
  "sandbox_id": 1,
  "analyst_name": "Analyst",
  "sample_reference": "sample-123",
  "starts_at": "2026-10-26T10:00:00Z",
  "ends_at": "2026-10-26T11:00:00Z"
}
```

Validation failures return `422` with field errors. A concurrent slot conflict that reaches the database unique constraint returns `409`.

## Business rules and design decisions

- Times are stored and displayed in UTC. The UI labels every date and time accordingly; API timestamps with explicit offsets are converted to UTC before validation and persistence.
- A booking may be up to two hours long. Back-to-back reservations are allowed, but overlapping reservations are rejected.
- The daily `02:00–03:00 UTC` maintenance window is unavailable.
- Booking creation checks for conflicts inside a transaction and locks the sandbox row. The database also has a unique index for identical sandbox/start-time pairs.
- MySQL 8 row locking is recommended for multi-user deployments. SQLite is convenient for local development but does not provide equivalent row-level locking guarantees.
- Authentication is intentionally omitted, as allowed by the assessment. Analyst names are supplied by the requester.
- Bookings are cancelled by deleting them; editing and rescheduling are not included.

## Deployment

1. Provision PHP 8.2+, Composer, and MySQL 8. Configure the web server document root to Laravel's `public` directory.
2. Clone the repository and run `composer install --no-dev --optimize-autoloader`.
3. Create a production `.env` with at least `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, a generated `APP_KEY`, and the `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` settings.
4. Run `php artisan migrate --seed --force` to create the schema and seed example sandboxes. Remove or replace the sample seeder data as appropriate for the deployment.
5. Ensure `storage` and `bootstrap/cache` are writable by the web-server user, then run `php artisan config:cache`.
6. Serve the application through the configured web server; do not expose the project root or `.env` file.

## Assessment notes to complete

The assessment asks for the full AI prompt history, actual build start/end time, token usage, and a short note on AI corrections. These are specific to the submission process and are not available from the source code, so record them from your own tool history rather than estimating them.
