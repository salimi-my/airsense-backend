<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeploymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $status,
        public string $message,
        public int $duration,
        public string $timestamp,
        public ?string $appName = null,
        public ?string $commitUrl = null,
        public ?string $commitAuthor = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $isSuccess = $this->status === 'success';
        $appName = $this->appName ?? config('app.name');

        $commitHash = '';
        if (str_contains($this->message, '—')) {
            $commitHash = ' (#'.trim(explode(' — ', $this->message, 2)[0]).')';
        }

        $appPrefix = $appName ? "[$appName] " : '';
        $subject = $isSuccess
            ? $appPrefix.'✅ Deployment Successful'.$commitHash
            : $appPrefix.'❌ Deployment Failed'.$commitHash;
        $greeting = $isSuccess ? 'Deployment Completed!' : 'Deployment Failed!';

        $mailMessage = (new MailMessage)
            ->subject(trim($subject))
            ->greeting($greeting)
            ->line('**Application:** '.$appName)
            ->line('**Status:** '.ucfirst($this->status))
            ->line('**Message:** '.$this->message);

        if ($this->commitAuthor) {
            $mailMessage->line('**Author:** '.$this->commitAuthor);
        }

        $mailMessage
            ->line('**Duration:** '.$this->formatDuration($this->duration))
            ->line('**Timestamp:** '.$this->timestamp);

        if ($this->commitUrl) {
            $mailMessage->action('View Commit on GitHub', $this->commitUrl);
        }

        if ($isSuccess) {
            $mailMessage->line('Your application has been successfully deployed and is now live.');
        } else {
            $mailMessage->line('Please check your deployment logs for more details about the failure.');
        }

        return $mailMessage
            ->line('Best regards,')
            ->line(config('app.name').' Deployment System')
            ->salutation(' ');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'duration' => $this->duration,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * Format duration from seconds to human-readable format.
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' seconds';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $minutes.' minute'.($minutes > 1 ? 's' : '').
                ($remainingSeconds > 0 ? ' '.$remainingSeconds.' second'.($remainingSeconds > 1 ? 's' : '') : '');
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return $hours.' hour'.($hours > 1 ? 's' : '').
            ($remainingMinutes > 0 ? ' '.$remainingMinutes.' minute'.($remainingMinutes > 1 ? 's' : '') : '');
    }
}
