<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a reservation for a malware-analysis sandbox.
 */
class Booking extends Model
{
    protected $fillable = [
        'sandbox_id',
        'analyst_name',
        'sample_reference',
        'starts_at',
        'ends_at',
    ];

    // immutable_datetime avoids accidental mutation during slot math.
    protected $casts = [
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
    ];

    /**
     * Get the sandbox reserved by this booking.
     *
     * @return BelongsTo<Sandbox, $this> The related sandbox.
     */
    public function sandbox(): BelongsTo
    {
        return $this->belongsTo(Sandbox::class);
    }
}
