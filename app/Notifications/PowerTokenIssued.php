<?php

namespace App\Notifications;

use App\Models\PowerPurchase;
use App\Models\User;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

class PowerTokenIssued extends AppNotification
{
    protected string $category = 'power';

    protected bool $essential = true;

    public function __construct(public PowerPurchase $purchase) {}

    protected function title(): string
    {
        return 'Your electricity token is ready';
    }

    protected function body(): string
    {
        $units = $this->purchase->token?->units;

        return Money::format($this->purchase->vend_amount).' for meter '.$this->purchase->meter_number.($units ? " · {$units} kWh" : '').'. Open the app to view and copy your token.';
    }

    protected function url(): ?string
    {
        return route('power.purchases.show', $this->purchase);
    }

    protected function icon(): string
    {
        return 'zap';
    }

    protected function estateId(): ?int
    {
        return $this->purchase->estate_id;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $token = $this->purchase->token;

        return (new MailMessage)
            ->subject('Electricity token · '.$this->purchase->meter_number)
            ->greeting('Hello '.$notifiable->firstName().',')
            ->line('Your electricity purchase was successful.')
            ->line('**Token:** '.trim(chunk_split((string) $token?->token, 4, ' ')))
            ->line('Meter: '.$this->purchase->meter_number.($token?->units ? ' · Units: '.$token->units.' '.$token->unit_label : ''))
            ->line('Amount: '.Money::format($this->purchase->vend_amount).' (total paid '.Money::format($this->purchase->amount_paid).')')
            ->line('Reference: '.$this->purchase->reference)
            ->action('View receipt', $this->url());
    }
}
