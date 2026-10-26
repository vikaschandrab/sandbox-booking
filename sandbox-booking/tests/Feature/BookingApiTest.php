<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Sandbox;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-25 00:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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

    public function test_a_booking_can_be_created_and_adjacent_bookings_are_allowed(): void
    {
        $sandbox = Sandbox::create(['name' => 'windows-1', 'os_family' => 'windows']);

        $this->postJson('/api/bookings', $this->bookingData($sandbox, '09:00', '11:00'))
            ->assertCreated()
            ->assertJsonPath('data.analyst_name', 'Analyst');

        $this->postJson('/api/bookings', $this->bookingData($sandbox, '11:00', '12:00'))
            ->assertCreated();
    }

    public function test_the_booking_page_contains_the_day_view_and_booking_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Sandbox bookings by hour')
            ->assertSee('Analyst name')
            ->assertSee('Sample reference')
            ->assertSee('Create booking');
    }

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

    public function test_bookings_longer_than_two_hours_are_rejected(): void
    {
        $sandbox = Sandbox::create(['name' => 'linux-1', 'os_family' => 'linux']);

        $this->postJson('/api/bookings', $this->bookingData($sandbox, '09:00', '11:01'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_bookings_overlapping_utc_maintenance_are_rejected(): void
    {
        $sandbox = Sandbox::create(['name' => 'linux-1', 'os_family' => 'linux']);

        $this->postJson('/api/bookings', $this->bookingData($sandbox, '02:30', '03:30'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

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
