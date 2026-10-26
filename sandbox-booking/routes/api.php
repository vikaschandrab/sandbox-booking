<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\SandboxController;
use Illuminate\Support\Facades\Route;

Route::get('/sandboxes', [SandboxController::class, 'index']);
Route::get('/bookings', [BookingController::class, 'index']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
