<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sandbox;
use Illuminate\Http\JsonResponse;

/**
 * Provides API endpoints for sandbox inventory.
 */
class SandboxController extends Controller
{
    /**
     * List available sandboxes in name order.
     *
     * @return JsonResponse The sandbox inventory.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Sandbox::query()
                ->orderBy('name')
                ->get(['id', 'name', 'os_family']),
        ]);
    }
}
