<?php

namespace App\Notifications;

use App\Models\EstateInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EstateInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public EstateInvitation $invitation, public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $estate = $this->invitation->estate;

        return (new MailMessage)
            ->subject("You're invited to {$estate->name} on PowerNest Estate")
            ->greeting('Hello'.($this->invitation->name ? ' '.$this->invitation->name : '').',')
            ->line("{$estate->name} has invited you to join as {$this->invitation->role->label()}".($this->invitation->property ? " for {$this->invitation->property->identifier}." : '.'))
            ->line('Use the button below to create your account or sign in. The link expires in 7 days and can only be used once.')
            ->action('Accept invitation', route('invitations.show', $this->token))
            ->line('If you were not expecting this, you can ignore this email.');
    }
}
