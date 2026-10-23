<?php

namespace App\Services\Operations;

use App\Enums\Permission;
use App\Models\Estate;
use App\Models\LostFoundItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class LostFoundService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{type: string, title: string, description?: ?string, location?: ?string}  $data
     */
    public function report(User $user, Estate $estate, array $data): LostFoundItem
    {
        $item = LostFoundItem::create([
            'estate_id' => $estate->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'status' => 'open',
            'reported_by' => $user->id,
        ]);

        $this->audit->record('lost_found.reported', $item, ['type' => $item->type], $estate, $user);

        return $item;
    }

    /**
     * The reporter or security management can close an item once it is returned or claimed.
     */
    public function resolve(LostFoundItem $item, User $actor): void
    {
        $allowed = $item->reported_by === $actor->id || $actor->hasEstatePermission($item->estate, Permission::IncidentsManage);
        abort_unless($allowed, 403);

        if ($item->status !== 'open') {
            throw ValidationException::withMessages(['item' => 'This item is already closed.']);
        }

        $item->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->audit->record('lost_found.resolved', $item, [], $item->estate_id, $actor);
    }
}
