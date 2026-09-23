<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic in-app notification with an optional, content-free email. Emails never
 * include private content (tip text, comment bodies) — only a prompt to sign in.
 */
class PlatformNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly array $data = [],
        public readonly ?string $url = null,
        public readonly ?string $emailCategory = null,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        $channels = ['database'];
        if ($this->emailCategory !== null && $notifiable->hasVerifiedEmail() && $notifiable->wantsEmailFor($this->emailCategory)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'url' => $this->url] + $this->data;
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title.' — Onushondhan')
            ->line($this->title.'.')
            ->line('Sign in to Onushondhan to view the details.')
            ->action('Open Onushondhan', config('onushondhan.frontend_url').'/account/notifications')
            ->line('You can change email notification preferences in your account settings.');
    }
}
