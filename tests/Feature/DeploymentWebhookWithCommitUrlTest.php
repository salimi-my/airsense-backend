<?php

namespace Tests\Feature;

use App\Notifications\DeploymentNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeploymentWebhookWithCommitUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.deployment_token', 'test-deployment-token');
        Config::set('app.deployment_notification_emails', ['admin@example.com']);
    }

    public function test_deployment_webhook_accepts_valid_commit_url(): void
    {
        Notification::fake();

        $commitUrl = 'https://github.com/username/repository/commit/abc123def';

        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'abc123 — Fixed bug in authentication',
            'duration' => 120,
            'timestamp' => '2024-02-12 10:00:00',
            'app_name' => 'Test App',
            'commit_url' => $commitUrl,
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Webhook received and notification sent successfully',
            ]);

        Notification::assertSentOnDemand(
            DeploymentNotification::class,
            function (DeploymentNotification $notification) use ($commitUrl) {
                return $notification->status === 'success'
                    && $notification->commitUrl === $commitUrl;
            }
        );
    }

    public function test_deployment_webhook_validates_commit_url_format(): void
    {
        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'abc123 — Fixed bug in authentication',
            'duration' => 120,
            'timestamp' => '2024-02-12 10:00:00',
            'commit_url' => 'not-a-valid-url',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['commit_url']);
    }

    public function test_deployment_webhook_works_without_commit_url(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'abc123 — Fixed bug in authentication',
            'duration' => 120,
            'timestamp' => '2024-02-12 10:00:00',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(200);

        Notification::assertSentOnDemand(
            DeploymentNotification::class,
            function (DeploymentNotification $notification) {
                return $notification->status === 'success'
                    && $notification->commitUrl === null;
            }
        );
    }

    public function test_deployment_notification_includes_commit_url_button(): void
    {
        $commitUrl = 'https://github.com/username/repository/commit/abc123def';

        $notification = new DeploymentNotification(
            status: 'success',
            message: 'abc123 — Fixed bug in authentication',
            duration: 120,
            timestamp: '2024-02-12 10:00:00',
            appName: 'Test App',
            commitUrl: $commitUrl
        );

        $mailMessage = $notification->toMail((object) ['email' => 'test@example.com']);

        $this->assertStringContainsString('View Commit on GitHub', $mailMessage->render());
        $this->assertEquals($commitUrl, $mailMessage->actionUrl);
        $this->assertEquals('View Commit on GitHub', $mailMessage->actionText);
    }

    public function test_deployment_notification_works_without_commit_url(): void
    {
        $notification = new DeploymentNotification(
            status: 'success',
            message: 'abc123 — Fixed bug in authentication',
            duration: 120,
            timestamp: '2024-02-12 10:00:00',
            appName: 'Test App',
            commitUrl: null
        );

        $mailMessage = $notification->toMail((object) ['email' => 'test@example.com']);

        $this->assertNull($mailMessage->actionUrl);
    }

    public function test_deployment_webhook_accepts_commit_author(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/deployment-webhook', [
            'status' => 'success',
            'message' => 'abc123 — Fixed bug in authentication',
            'duration' => 120,
            'timestamp' => '2024-02-12 10:00:00',
            'app_name' => 'Test App',
            'commit_url' => 'https://github.com/username/repository/commit/abc123def',
            'commit_author' => 'John Doe',
        ], [
            'X-Deployment-Token' => 'test-deployment-token',
        ]);

        $response->assertStatus(200);

        Notification::assertSentOnDemand(
            DeploymentNotification::class,
            function (DeploymentNotification $notification) {
                return $notification->status === 'success'
                    && $notification->commitAuthor === 'John Doe';
            }
        );
    }

    public function test_deployment_notification_includes_commit_author(): void
    {
        $notification = new DeploymentNotification(
            status: 'success',
            message: 'abc123 — Fixed bug in authentication',
            duration: 120,
            timestamp: '2024-02-12 10:00:00',
            appName: 'Test App',
            commitUrl: 'https://github.com/username/repository/commit/abc123',
            commitAuthor: 'John Doe'
        );

        $mailMessage = $notification->toMail((object) ['email' => 'test@example.com']);

        $this->assertStringContainsString('John Doe', $mailMessage->render());
    }
}
