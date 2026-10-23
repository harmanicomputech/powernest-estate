<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Enums\Priority;
use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MaintenanceRequest extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => MaintenanceStatus::class,
            'priority' => Priority::class,
            'due_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_preventive' => 'boolean',
            'labour_cost' => 'integer',
            'parts_cost' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(MaintenanceUpdate::class)->orderBy('id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->status !== MaintenanceStatus::Completed && $this->due_at !== null && $this->due_at->isPast();
    }
}
