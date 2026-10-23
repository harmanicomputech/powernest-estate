<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for in-app + email notifications. In-app delivery is always on;
 * email respects the user's preference for non-essential categories.
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Preference category (visitors, power, billing, maintenance, announcements, operations). */
    protected string $category = 'general';

    /** Essential notices (payments, tokens, security) always email. */
    protected bool $essential = false;

    protected bool $mail = true;

    abstract protected function title(): string;

    abstract protected function body(): string;

    abstract protected function url(): ?string;

    protected function icon(): string
    {
        return 'bell';
    }

    protected function estateId(): ?int
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $channels = ['database'];

        if ($this->mail && ($this->essential || $notifiable->wantsNotification($this->category, 'mail'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'icon' => $this->icon(),
            'category' => $this->category,
            'estate_id' => $this->estateId(),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->title())
            ->greeting('Hello '.$notifiable->firstName().',')
            ->line($this->body());

        if ($url = $this->url()) {
            $message->action('Open PowerNest Estate', $url);
        }

        return $message;
    }
}
