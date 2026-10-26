<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sandbox booking</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; color: #172033; background: #f4f6fa; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        main { max-width: 1200px; margin: 0 auto; padding: 32px 20px; }
        h1, h2 { margin-top: 0; }
        h1 { margin-bottom: 8px; }
        .intro { color: #536178; margin: 0 0 24px; }
        section { background: #fff; border: 1px solid #dce2eb; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .toolbar { display: flex; align-items: end; gap: 12px; flex-wrap: wrap; }
        label { display: grid; gap: 6px; font-size: .9rem; font-weight: 600; }
        input, select, button { font: inherit; }
        input, select { width: 100%; min-height: 40px; border: 1px solid #b8c2d1; border-radius: 6px; padding: 8px 10px; background: #fff; }
        button { border: 0; border-radius: 6px; padding: 10px 14px; background: #2457a7; color: #fff; cursor: pointer; }
        button:disabled { opacity: .55; cursor: wait; }
        button.cancel { background: #a83232; padding: 6px 9px; font-size: .85rem; margin-top: 6px; }
        .date-label { width: 190px; }
        .status { min-height: 1.5em; color: #34445e; }
        .status.error { color: #a21d1d; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 680px; }
        th, td { border: 1px solid #dce2eb; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #f1f4f9; }
        th:first-child { min-width: 100px; }
        td { height: 68px; }
        .booking { display: block; padding: 7px; border-radius: 5px; background: #e7efff; font-size: .85rem; }
        .booking + .booking { margin-top: 6px; }
        .booking strong, .booking span { display: block; }
        .muted { color: #65748b; font-size: .85rem; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; }
        .form-grid button { align-self: end; min-height: 40px; }
    </style>
</head>
<body>
<main>
    <h1>Malware analysis sandbox booking</h1>
    <p class="intro">Reserve one sandbox at a time. All times are shown and submitted in UTC.</p>

    <section>
        <div class="toolbar">
            <label class="date-label" for="view-date">Day to view (UTC)
                <input id="view-date" type="date">
            </label>
            <p id="status" class="status" role="status" aria-live="polite"></p>
        </div>
        <div class="table-wrap">
            <table id="schedule" aria-label="Sandbox bookings by hour">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section>
        <h2>Book a sandbox</h2>
        <form id="booking-form">
            <div class="form-grid">
                <label for="sandbox-id">Sandbox
                    <select id="sandbox-id" name="sandbox_id" required></select>
                </label>
                <label for="booking-date">Date (UTC)
                    <input id="booking-date" name="date" type="date" required>
                </label>
                <label for="starts-at">Start time (UTC)
                    <input id="starts-at" name="starts_at" type="time" required>
                </label>
                <label for="ends-at">End time (UTC)
                    <input id="ends-at" name="ends_at" type="time" required>
                </label>
                <label for="analyst-name">Analyst name
                    <input id="analyst-name" name="analyst_name" type="text" maxlength="255" required>
                </label>
                <label for="sample-reference">Sample reference
                    <input id="sample-reference" name="sample_reference" type="text" maxlength="255" required>
                </label>
                <button type="submit">Create booking</button>
            </div>
        </form>
    </section>
</main>

<script>
    const dateInput = document.querySelector('#view-date');
    const bookingDateInput = document.querySelector('#booking-date');
    const sandboxSelect = document.querySelector('#sandbox-id');
    const schedule = document.querySelector('#schedule');
    const status = document.querySelector('#status');
    const form = document.querySelector('#booking-form');
    let sandboxes = [];
    let bookings = [];

    function setStatus(message, isError = false) {
        status.textContent = message;
        status.classList.toggle('error', isError);
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            ...options,
        });
        const body = response.status === 204 ? null : await response.json();

        if (!response.ok) {
            const validationMessages = body?.errors
                ? Object.values(body.errors).flat().join(' ')
                : '';
            throw new Error(validationMessages || body?.message || 'The request could not be completed.');
        }

        return body;
    }

    function renderSchedule() {
        const head = schedule.querySelector('thead');
        const body = schedule.querySelector('tbody');
        head.innerHTML = '';
        body.innerHTML = '';

        const header = document.createElement('tr');
        header.innerHTML = '<th scope="col">Time (UTC)</th>';
        for (const sandbox of sandboxes) {
            const cell = document.createElement('th');
            cell.scope = 'col';
            cell.textContent = `${sandbox.name} (${sandbox.os_family})`;
            header.append(cell);
        }
        head.append(header);

        for (let hour = 0; hour < 24; hour++) {
            const row = document.createElement('tr');
            const timeCell = document.createElement('th');
            timeCell.scope = 'row';
            timeCell.textContent = `${String(hour).padStart(2, '0')}:00`;
            row.append(timeCell);

            for (const sandbox of sandboxes) {
                const cell = document.createElement('td');
                const hourStart = Date.parse(`${dateInput.value}T${String(hour).padStart(2, '0')}:00:00Z`);
                const hourEnd = hourStart + 60 * 60 * 1000;
                const visibleBookings = bookings.filter((booking) => {
                    if (booking.sandbox_id !== sandbox.id) return false;
                    const start = Date.parse(booking.starts_at.replace(' ', 'T') + (booking.starts_at.endsWith('Z') ? '' : 'Z'));
                    const end = Date.parse(booking.ends_at.replace(' ', 'T') + (booking.ends_at.endsWith('Z') ? '' : 'Z'));
                    const bookingDate = new Date(start).toISOString().slice(0, 10);
                    const displayHour = bookingDate < dateInput.value ? 0 : new Date(start).getUTCHours();

                    return displayHour === hour && start < hourEnd && end > hourStart;
                });

                for (const booking of visibleBookings) {
                    const event = document.createElement('div');
                    event.className = 'booking';
                    const start = new Date(booking.starts_at.replace(' ', 'T') + (booking.starts_at.endsWith('Z') ? '' : 'Z'));
                    const end = new Date(booking.ends_at.replace(' ', 'T') + (booking.ends_at.endsWith('Z') ? '' : 'Z'));
                    const isPreviousDay = start.toISOString().slice(0, 10) < dateInput.value;
                    const timeRange = isPreviousDay
                        ? `Continues until ${end.toISOString().slice(11, 16)}`
                        : `${start.toISOString().slice(11, 16)}–${end.toISOString().slice(11, 16)}`;
                    event.innerHTML = `<strong>${escapeHtml(timeRange)} UTC</strong><span>${escapeHtml(booking.analyst_name)}</span><span>${escapeHtml(booking.sample_reference)}</span><button class="cancel" type="button" data-booking-id="${Number(booking.id)}">Cancel</button>`;
                    cell.append(event);
                }
                row.append(cell);
            }
            body.append(row);
        }

        if (sandboxes.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="2" class="muted">No sandboxes are available.</td>';
            body.append(row);
        } else if (bookings.length === 0) {
            setStatus('No bookings for this date.');
        }
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (character) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
        })[character]);
    }

    async function loadSchedule() {
        setStatus('Loading schedule…');
        try {
            const result = await request(`/api/bookings?date=${encodeURIComponent(dateInput.value)}`);
            bookings = result.data;
            renderSchedule();
            setStatus(bookings.length ? `${bookings.length} booking(s) shown.` : 'No bookings for this date.');
        } catch (error) {
            setStatus(error.message, true);
        }
    }

    async function loadSandboxes() {
        const result = await request('/api/sandboxes');
        sandboxes = result.data;
        sandboxSelect.innerHTML = '';
        for (const sandbox of sandboxes) {
            const option = document.createElement('option');
            option.value = sandbox.id;
            option.textContent = `${sandbox.name} (${sandbox.os_family})`;
            sandboxSelect.append(option);
        }
        renderSchedule();
    }

    dateInput.value = new Date().toISOString().slice(0, 10);
    bookingDateInput.value = dateInput.value;
    dateInput.addEventListener('change', () => {
        bookingDateInput.value = dateInput.value;
        loadSchedule();
    });
    bookingDateInput.addEventListener('change', () => {
        dateInput.value = bookingDateInput.value;
        loadSchedule();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        const date = data.get('date');
        try {
            await request('/api/bookings', {
                method: 'POST',
                body: JSON.stringify({
                    sandbox_id: Number(data.get('sandbox_id')),
                    analyst_name: data.get('analyst_name'),
                    sample_reference: data.get('sample_reference'),
                    starts_at: `${date}T${data.get('starts_at')}:00Z`,
                    ends_at: `${date}T${data.get('ends_at')}:00Z`,
                }),
            });
            setStatus('Booking created successfully.');
            dateInput.value = date;
            await loadSchedule();
        } catch (error) {
            setStatus(error.message, true);
        }
    });

    schedule.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-booking-id]');
        if (!button) return;

        button.disabled = true;
        try {
            await request(`/api/bookings/${button.dataset.bookingId}`, { method: 'DELETE' });
            setStatus('Booking cancelled successfully.');
            await loadSchedule();
        } catch (error) {
            button.disabled = false;
            setStatus(error.message, true);
        }
    });

    Promise.all([loadSandboxes(), loadSchedule()]).catch((error) => setStatus(error.message, true));
</script>
</body>
</html>
