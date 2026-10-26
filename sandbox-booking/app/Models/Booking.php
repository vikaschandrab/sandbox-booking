<?php

namespace App\Models;

use App\Models\Sandbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'ends_at'   => 'immutable_datetime',
    ];

    public function sandbox(): BelongsTo
    {
        return $this->belongsTo(Sandbox::class);
    }
}
