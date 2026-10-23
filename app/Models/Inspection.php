<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A completed or in-progress run of an inspection checklist.
 * results: list<array{item: string, result: ?string, note: ?string}> where result is pass|fail|na.
 */
class Inspection extends Model
{
    use BelongsToEstate;

    protected $guarded = ['id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'in_progress', 'failed_count' => 0];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'inspection_template_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }
}
