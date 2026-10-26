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

The feature tests cover the page, APIs, UTC date filtering, adjacent and overlapping bookings, the two-hour limit, the maintenance window, cancellation, offset-to-UTC normalization, and repeatable sandbox seeding.

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
- Booking creation checks for conflicts inside a transaction and locks the sandbox row before checking and inserting. Concurrent requests for one sandbox therefore serialize on that row when the database supports row-level locks. A unique index also protects identical sandbox/start-time pairs.
- Use MySQL 8 with InnoDB (or a compatible InnoDB-based database such as MariaDB) and a concurrent web server for multi-analyst deployments. SQLite is convenient for local development and tests but does not provide equivalent row-level locking guarantees.
- Authentication is intentionally omitted, as allowed by the assessment. Analyst names are supplied by the requester.
- Bookings are cancelled by deleting them; editing and rescheduling are not included.

### Verify concurrent bookings

The regular test suite uses in-memory SQLite and does not simulate concurrent database transactions. To smoke-test concurrency, configure `.env` to use MySQL 8/InnoDB (or compatible MariaDB), migrate and seed the database, and run the app behind a server that can handle concurrent requests (such as Apache or PHP-FPM). Do not use SQLite or Laravel's single-process development server for this check.

In PowerShell, submit two identical requests at once. Replace the sandbox ID if needed:

```powershell
$start = [DateTime]::UtcNow.AddDays(1).Date.AddHours(10).ToString("yyyy-MM-ddTHH:mm:ssZ")
$end = [DateTime]::UtcNow.AddDays(1).Date.AddHours(11).ToString("yyyy-MM-ddTHH:mm:ssZ")
$payload = @{
    sandbox_id = 1
    analyst_name = "Concurrency test"
    sample_reference = "concurrency-smoke-test"
    starts_at = $start
    ends_at = $end
} | ConvertTo-Json

$jobs = 1..2 | ForEach-Object {
    Start-Job -ArgumentList $payload -ScriptBlock {
        param($body)
        try {
            (Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/bookings" `
                -Method Post -ContentType "application/json" `
                -Headers @{ Accept = "application/json" } -Body $body).StatusCode
        } catch {
            [int]$_.Exception.Response.StatusCode
        }
    }
}
$statuses = $jobs | Receive-Job -Wait -AutoRemoveJob
$statuses
```

Expected result: exactly one `201` and one rejection (`409` or `422`). Clean up the successful test booking from the UI or with `DELETE /api/bookings/{id}`.

## Deployment

1. Provision PHP 8.2+, Composer, and MySQL 8. Configure the web server document root to Laravel's `public` directory.
2. Clone the repository and run `composer install --no-dev --optimize-autoloader`.
3. Create a production `.env` with at least `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, a generated `APP_KEY`, and the `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` settings.
4. Run `php artisan migrate --seed --force` to create the schema and seed example sandboxes. Remove or replace the sample seeder data as appropriate for the deployment.
5. Ensure `storage` and `bootstrap/cache` are writable by the web-server user, then run `php artisan config:cache`.
6. Serve the application through the configured web server; do not expose the project root or `.env` file.

## What I would improve with more time

- Add authentication and analyst-specific permissions, while keeping the current no-auth setup for the assessment.
- Let analysts choose a display timezone and cover daylight-saving transitions in the calendar UI.
- Add an audit trail for cancellations and rescheduling, plus optional booking notifications.

## AI prompts used in this conversation

The user prompts recorded in this conversation, in order:

1. “based on the requirement in the file I have developed the code. Now please check and missing code development I want it to be working code”
2. “Now I want steps to run all the steps”
3. “give me git commit details”
4. “check every requirement is met from pdf”
5. “now I want missing and not met, **Partially verified** thngs to met and everythins is implemented
git hub https://github.com/vikaschandrab/sandbox-booking/tree/main/sandbox-booking”

If you used other AI tools or prompts outside this conversation, add their prompts here too so this list is complete.

## Build time and token usage

- Build date: 2026-10-26.
- The conversation records the initial implementation request at 19:41:58 (UTC+05:30), and the working app had been delivered by 19:50:52 (UTC+05:30). The exact completion time was not captured; 8 minutes 54 seconds is the maximum elapsed interval supported by those timestamps, not a measured build duration.
- Input/output token totals: unavailable; the usage dashboard is not accessible.

## AI correction note

The initial implementation was incomplete: the API routes were not registered, the sandbox controller and booking read/cancel endpoints were stubs, booking requests were denied by the request authorization method, and the booking service referenced missing business-rule checks. Review identified these gaps; the implementation was completed and covered by feature tests for booking rules, APIs, UTC handling, and cancellation.
