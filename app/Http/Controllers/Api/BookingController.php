<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Handles API requests for booking schedules and reservations.
 */
class BookingController extends Controller
{
    /**
     * List bookings that overlap the requested UTC date.
     *
     * @param  Request  $request  The incoming request containing an optional date.
     * @return JsonResponse The bookings overlapping the requested day.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $date = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $validated['date'] ?? CarbonImmutable::now('UTC')->toDateString(),
            'UTC',
        );

        return response()->json([
            'data' => Booking::query()
                ->with('sandbox:id,name,os_family')
                ->where('starts_at', '<', $date->addDay())
                ->where('ends_at', '>', $date)
                ->orderBy('starts_at')
                ->get(),
        ]);
    }

    /**
     * Create a booking after validating its business rules.
     *
     * @param  StoreBookingRequest  $request  The validated booking details.
     * @param  BookingService  $service  The service that enforces booking rules.
     * @return JsonResponse The created booking or a validation/conflict response.
     */
    public function store(StoreBookingRequest $request, BookingService $service): JsonResponse
    {
        try {
            $booking = $service->create($request->validated());

            return response()->json([
                'message' => 'Booking created successfully.',
                'data' => $booking->load('sandbox'),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Booking rejected.',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            // Handle an integrity-constraint race, such as an identical start time.
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'The booking conflicts with a recent change. Refresh the schedule and try again.',
                ], 409);
            }
            throw $e;
        }
    }

    /**
     * Cancel an existing booking.
     *
     * @param  Booking  $booking  The booking to cancel.
     * @return JsonResponse A confirmation response.
     */
    public function destroy(Booking $booking): JsonResponse
    {
        $booking->delete();

        return response()->json([
            'message' => 'Booking cancelled successfully.',
        ]);
    }
}
