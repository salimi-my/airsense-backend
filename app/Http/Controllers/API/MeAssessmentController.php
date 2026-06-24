<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MeAssessmentController extends AppBaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 10), 50);

        $assessments = Assessment::query()
            ->where('user_id', Auth::id())
            ->with('station:id,name,city')
            ->latest('assessed_at')
            ->paginate($perPage);

        return $this->sendResponse($assessments, 'Your assessments retrieved successfully');
    }
}
