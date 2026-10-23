<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassScan extends Model
{
    use BelongsToEstate, HasFactory, MassPrunable;

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(VisitorPass::class, 'visitor_pass_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    /**
     * Retention: see config/powernest.php (retention.pass_scans_months).
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths((int) config('powernest.retention.pass_scans_months')));
    }
}
