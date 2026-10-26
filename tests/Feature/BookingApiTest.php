<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Sandbox;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Verifies booking APIs, validation rules, and related user flows.
 */
class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set a fixed current time for deterministic booking tests.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-25 00:00:00 UTC');
    }

    /**
     * Restore the system clock after each test.
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Verify sandbox and UTC-day booking listing endpoints.
     */
    public function test_sandboxes_and_bookings_can_be_listed_for_a_utc_day(): void
    {
        $sandbox = Sandbox::create(['name' => 'linux-1', 'os_family' => 'linux']);
        Booking::create([
            'sandbox_id' => $sandbox->id,
            'analyst_name' => 'Analyst',
            'sample_reference' => 'sample-1',
            'starts_at' => '2026-10-25 23:30:00',
            'ends_at' => '2026-10-26 00:30:00',
        ]);

        $this->getJson('/api/sandboxes')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'linux-1');

        $this->getJson('/api/bookings?date=2026-10-26')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sandbox.name', 'linux-1');
    }

    /**
     * Verify a booking can be created and an adjacent booking is allowed.
     */
    public function test_a_booking_can_be_created_and_adjacent_bookings_are_allowed(): void
    {
        $sandbox = Sandbox::create(['name' => 'windows-1', 'os_family' => 'windows']);

        $this->postJson('/api/bookings', $this->bookingData($sandbox, '09:00', '11:00'))
            ->assertCreated()
            ->assertJsonPath('data.analyst_name', 'Analyst');

        $this->postJson('/api/bookings', $this->bookingData($sandbox, '11:00', '12:00'))
            ->assertCreated();
    }

    /**
     * Verify that the booking page exposes its schedule and booking form.
     */
    public function test_the_booking_page_contains_the_day_view_and_booking_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Sandbox bookings by hour')
            ->assertSee('Analyst name')
            ->assertSee('Sample reference')
            ->assertSee('Create booking');
    }

    /**
     * Verify that sandbox seeding can be repeated without duplicate rows.
     */
    public function test_sandbox_seeder_can_be_run_more_than_once(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SandboxSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SandboxSeeder']);

        $this->assertSame(5, Sandbox::count());
    }

    /**
     * Verify overlapping bookings are rejected and cancellation frees the slot.
     */
    public function test_overlapping_bookings_are_rejected_but_cancellation_frees_the_slot(): void
    {
        $sandbox = Sandbox::create(['name' => 'windows-1', 'os_family' => 'windows']);
        $data = $this->bookingData($sandbox, '10:00', '11:00');

        $created = $this->postJson('/api/bookings', $data)->assertCreated();
        $this->postJson('/api/bookings', $this->bookingData($sandbox, '10:30', '11:30'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');

        $this->deleteJson('/api/bookings/'.$created->json('data.id'))
            ->assertOk()
            ->assertJsonPath('message', 'Booking cancelled successfully.');

        $this->postJson('/api/bookings', $this->bookingData($sandbox, '10:30', '11:30'))
            ->assertCreated();
    }

    /**
     * Verify that bookings longer than two hours are rejected.
     */
    public function test_bookings_longer_than_two_hours_are_rejected(): void
    {
        $sandbox = Sandbox::create(['name' => 'linux-1', 'os_family' => 'linux']);

        $this->postJson('/api/bookings', $this->bookingData($sandbox, '09:00', '11:01'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
    }

    /**
     * Verify that bookings overlapping the UTC maintenance window are rejected.
     */
    public function test_bookings_overlapping_utc_maintenance_are_rejected(): void
    {
        $sandbox = Sandbox::create(['name' => 'linux-1', 'os_family' => 'linux']);

        $this->postJson('/api/bookings', $this->bookingData($sandbox, '02:30', '03:30'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    /**
     * Verify that timezone offsets are converted to UTC before validation.
     */
    public function test_timezone_offsets_are_normalized_to_utc_before_business_rules(): void
    {
        $sandbox = Sandbox::create(['name' => 'linux-1', 'os_family' => 'linux']);
        $data = $this->bookingData($sandbox, '04:00', '05:00');
        $data['starts_at'] = '2026-10-26T04:00:00+02:00';
        $data['ends_at'] = '2026-10-26T05:00:00+02:00';

        $this->postJson('/api/bookings', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    /**
     * Build valid booking input for a sandbox and UTC time range.
     *
     * @param  Sandbox  $sandbox  The sandbox to reserve.
     * @param  string  $start  The UTC start time in HH:MM format.
     * @param  string  $end  The UTC end time in HH:MM format.
     * @return array<string, int|string> The booking request payload.
     */
    private function bookingData(Sandbox $sandbox, string $start, string $end): array
    {
        return [
            'sandbox_id' => $sandbox->id,
            'analyst_name' => 'Analyst',
            'sample_reference' => 'sample-1',
            'starts_at' => "2026-10-26T{$start}:00Z",
            'ends_at' => "2026-10-26T{$end}:00Z",
        ];
    }
}
