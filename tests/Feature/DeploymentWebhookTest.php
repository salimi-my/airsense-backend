<?php

namespace Tests\Feature;

use App\Notifications\DeploymentNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeploymentWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.deployment_token', 'test-deployment-token');
        Config::set('app.deployment_notification_emails', ['admin@example.com', 'devops@example.com']);
    }

    public function test_deployment_webhook_requires_valid_token(): void
    {
        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'Deployment completed successfully',
            'duration' => 120,
            'timestamp' => '2024-02-12 10:00:00',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthorized. Invalid deployment token.',
            ]);
    }

    public function test_deployment_webhook_with_invalid_token(): void
    {
        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'Deployment completed successfully',
            'duration' => 120,
            'timestamp' => '2024-02-12 10:00:00',
        ], [
            'X-Deployment-Token' => 'invalid-token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthorized. Invalid deployment token.',
            ]);
    }

    public function test_deployment_webhook_validation_requires_status(): void
    {
        $response = $this->postJson('/api/deployment-webhook', [
            'message' => 'Deployment completed successfully',
            'duration' => 120,
            'timestamp' => '2024-02-12 10:00:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_deployment_webhook_validation_requires_message(): void
    {
        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'duration' => 120,
            'timestamp' => '2024-02-12 10:00:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_deployment_webhook_validation_requires_duration(): void
    {
        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'Deployment completed successfully',
            'timestamp' => '2024-02-12 10:00:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['duration']);
    }

    public function test_deployment_webhook_validation_requires_timestamp(): void
    {
        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'Deployment completed successfully',
            'duration' => 120,
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['timestamp']);
    }

    public function test_deployment_webhook_validation_status_must_be_valid_value(): void
    {
        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'invalid-status',
            'message' => 'Deployment completed successfully',
            'duration' => 120,
            'timestamp' => '2024-02-12 10:00:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_deployment_webhook_success_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'Deployment completed successfully',
            'duration' => 120,
            'timestamp' => '2024-02-12 10:00:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Webhook received and notification sent successfully',
            ]);

        Notification::assertSentOnDemand(
            DeploymentNotification::class,
            function (DeploymentNotification $notification, array $channels, object $notifiable) {
                return $notifiable->routes['mail'] === ['admin@example.com', 'devops@example.com']
                    && $notification->status === 'success'
                    && $notification->message === 'Deployment completed successfully'
                    && $notification->duration === 120
                    && $notification->timestamp === '2024-02-12 10:00:00';
            }
        );
    }

    public function test_deployment_webhook_failure_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'failed',
            'message' => 'Deployment failed at line 42',
            'duration' => 45,
            'timestamp' => '2024-02-12 10:05:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Webhook received and notification sent successfully',
            ]);

        Notification::assertSentOnDemand(
            DeploymentNotification::class,
            function (DeploymentNotification $notification, array $channels, object $notifiable) {
                return $notifiable->routes['mail'] === ['admin@example.com', 'devops@example.com']
                    && $notification->status === 'failed'
                    && $notification->message === 'Deployment failed at line 42'
                    && $notification->duration === 45
                    && $notification->timestamp === '2024-02-12 10:05:00';
            }
        );
    }

    public function test_deployment_webhook_with_no_configured_emails(): void
    {
        Config::set('app.deployment_notification_emails', []);

        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'Deployment completed successfully',
            'duration' => 120,
            'timestamp' => '2024-02-12 10:00:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Webhook received but no notification emails configured',
            ]);
    }

    public function test_deployment_webhook_handles_long_messages(): void
    {
        Notification::fake();

        $longMessage = str_repeat('This is a long deployment message. ', 50);

        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => substr($longMessage, 0, 1000),
            'duration' => 300,
            'timestamp' => '2024-02-12 10:00:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(200);

        Notification::assertSentOnDemand(DeploymentNotification::class);
    }

    public function test_deployment_webhook_handles_various_durations(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'Quick deployment',
            'duration' => 45,
            'timestamp' => '2024-02-12 10:00:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(200);

        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'Medium deployment',
            'duration' => 300,
            'timestamp' => '2024-02-12 10:05:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(200);

        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'Long deployment',
            'duration' => 7200,
            'timestamp' => '2024-02-12 12:00:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(200);

        Notification::assertSentOnDemandTimes(DeploymentNotification::class, 3);
    }
}
