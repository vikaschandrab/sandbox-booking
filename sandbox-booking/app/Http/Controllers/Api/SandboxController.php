<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sandbox;
use Illuminate\Http\JsonResponse;

class SandboxController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Sandbox::query()
                ->orderBy('name')
                ->get(['id', 'name', 'os_family']),
        ]);
    }
}
