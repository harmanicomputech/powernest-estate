<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class GatewayWebhookEvent extends Model
{
    use HasFactory, MassPrunable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Retention: see config/powernest.php (retention.webhook_events_months).
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths((int) config('powernest.retention.webhook_events_months')));
    }
}
