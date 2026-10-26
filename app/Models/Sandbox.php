<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents an available malware-analysis environment.
 */
class Sandbox extends Model
{
    protected $fillable = ['name', 'os_family'];

    /**
     * Get the bookings made for this sandbox.
     *
     * @return HasMany<Booking, $this> The related bookings.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
