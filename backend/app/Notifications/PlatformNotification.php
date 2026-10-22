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

    /**
     * @param  string  $title  translation key (English source text, may contain :placeholders)
     * @param  array<string, mixed>  $data
     * @param  array<string, string|int>  $titleParams
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly array $data = [],
        public readonly ?string $url = null,
        public readonly ?string $emailCategory = null,
        public readonly array $titleParams = [],
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
        // Rendered in the notifiable's preferred locale (Laravel switches locale while sending);
        // the source key is kept so the API can re-render it in the reader's current language.
        return ['kind' => $this->kind, 'title' => __($this->title, $this->titleParams), 'title_key' => $this->title, 'params' => $this->titleParams, 'url' => $this->url] + $this->data;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $title = __($this->title, $this->titleParams);

        return (new MailMessage)
            ->subject($title.' — '.__('Onushondhan'))
            ->line($title.'.')
            ->line(__('Sign in to Onushondhan to view the details.'))
            ->action(__('Open Onushondhan'), config('onushondhan.frontend_url').'/account/notifications')
            ->line(__('You can change email notification preferences in your account settings.'));
    }
}
