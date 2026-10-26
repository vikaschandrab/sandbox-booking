<?php

namespace App\Models;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sandbox extends Model
{
    protected $fillable = ['name', 'os_family'];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
