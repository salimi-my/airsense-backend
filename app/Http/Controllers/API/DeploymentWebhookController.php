<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeploymentWebhookRequest;
use App\Notifications\DeploymentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class DeploymentWebhookController extends Controller
{
    /**
     * Handle incoming deployment webhook.
     */
    public function handle(DeploymentWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Log::info('Deployment webhook received', [
            'status' => $validated['status'],
            'message' => $validated['message'],
            'duration' => $validated['duration'],
            'timestamp' => $validated['timestamp'],
        ]);

        $adminEmails = config('app.deployment_notification_emails');

        if (empty($adminEmails)) {
            Log::warning('No deployment notification emails configured');

            return response()->json([
                'message' => 'Webhook received but no notification emails configured',
            ], 200);
        }

        try {
            Notification::route('mail', $adminEmails)
                ->notify(new DeploymentNotification(
                    $validated['status'],
                    $validated['message'],
                    $validated['duration'],
                    $validated['timestamp'],
                    $validated['app_name'] ?? null,
                    $validated['commit_url'] ?? null,
                    $validated['commit_author'] ?? null
                ));

            Log::info('Deployment notification sent', [
                'recipients' => $adminEmails,
                'status' => $validated['status'],
            ]);

            return response()->json([
                'message' => 'Webhook received and notification sent successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to send deployment notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Webhook received but notification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
