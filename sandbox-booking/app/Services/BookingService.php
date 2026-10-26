<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Sandbox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function create(array $data): Booking
    {
        $startsAt = CarbonImmutable::parse($data['starts_at'], 'UTC')->utc();
        $endsAt = CarbonImmutable::parse($data['ends_at'], 'UTC')->utc();

        $this->assertWithinTwoHours($startsAt, $endsAt);
        $this->assertNotInMaintenanceWindow($startsAt, $endsAt);

        return DB::transaction(function () use ($data, $startsAt, $endsAt) {

            // 1. Lock the sandbox row so concurrent bookings for the same
            //    sandbox queue here. This is the serialisation point.
            $sandbox = Sandbox::query()
                ->whereKey($data['sandbox_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Overlap check INSIDE the transaction, after the lock.
            $clash = Booking::query()
                ->where('sandbox_id', $sandbox->id)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->lockForUpdate()
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages([
                    'starts_at' => 'This sandbox is already booked during that time.',
                ]);
            }

            // 3. Insert only after the lock and the check.
            return Booking::create([
                'sandbox_id' => $sandbox->id,
                'analyst_name' => $data['analyst_name'],
                'sample_reference' => $data['sample_reference'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        });
    }

    private function assertWithinTwoHours(CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        if ($endsAt->getTimestamp() - $startsAt->getTimestamp() > 2 * 60 * 60) {
            throw ValidationException::withMessages([
                'ends_at' => 'A booking cannot be longer than two hours.',
            ]);
        }
    }

    private function assertNotInMaintenanceWindow(CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        for ($day = $startsAt->startOfDay(); $day->lessThan($endsAt); $day = $day->addDay()) {
            $maintenanceStartsAt = $day->setTime(2, 0);
            $maintenanceEndsAt = $day->setTime(3, 0);

            if ($startsAt->lessThan($maintenanceEndsAt) && $endsAt->greaterThan($maintenanceStartsAt)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Bookings cannot overlap the 02:00–03:00 UTC maintenance window.',
                ]);
            }
        }
    }
}
