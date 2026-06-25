<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Models\Assessment;
use App\Models\Reading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLogController extends AppBaseController
{
    public function readings(Request $request): JsonResponse
    {
        if (! $this->isAdmin()) {
            return $this->sendError('Unauthorized', 403);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);

        $readings = Reading::query()
            ->with('station:id,name,city')
            ->orderByDesc('created_at')
            ->orderByDesc('fetched_at')
            ->paginate($perPage);

        return $this->sendResponse($readings, 'Readings log retrieved successfully');
    }

    public function assessments(Request $request): JsonResponse
    {
        if (! $this->isAdmin()) {
            return $this->sendError('Unauthorized', 403);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);

        $assessments = Assessment::query()
            ->with('station:id,name,city')
            ->latest('assessed_at')
            ->paginate($perPage);

        return $this->sendResponse($assessments, 'Assessments log retrieved successfully');
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role?->name === 'admin';
    }
}
