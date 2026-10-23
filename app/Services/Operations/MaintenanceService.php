<?php

namespace App\Services\Operations;

use App\Enums\MaintenanceStatus as Status;
use App\Enums\Permission;
use App\Enums\Priority;
use App\Models\Estate;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceUpdate;
use App\Models\User;
use App\Notifications\MaintenanceUpdated;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentStore;
use App\Support\Reference;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    public function __construct(private AuditLogger $audit, private DocumentStore $documents) {}

    /**
     * @param  array{property_id?: ?int, category: string, location?: ?string, title: string, description: string, priority?: ?string, preferred_time?: ?string}  $data
     * @param  list<UploadedFile>  $photos
     */
    public function submit(User $user, Estate $estate, array $data, array $photos = []): MaintenanceRequest
    {
        $isStaff = $user->hasEstatePermission($estate, Permission::MaintenanceManage);

        if (! empty($data['property_id']) && ! $isStaff && ! $user->authorizedProperties($estate)->whereKey($data['property_id'])->exists()) {
            throw ValidationException::withMessages(['property_id' => 'Choose a property you are linked to.']);
        }

        if (empty($data['property_id']) && empty($data['location'])) {
            throw ValidationException::withMessages(['location' => 'Tell us where the problem is.']);
        }

        $priority = Priority::tryFrom($data['priority'] ?? '') ?? Priority::Normal;

        return DB::transaction(function () use ($user, $estate, $data, $photos, $priority) {
            $request = MaintenanceRequest::create([
                'estate_id' => $estate->id,
                'reference' => $this->reference(),
                'property_id' => $data['property_id'] ?? null,
                'requested_by' => $user->id,
                'category' => $data['category'],
                'location' => $data['location'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $priority,
                'status' => Status::Submitted,
                'preferred_time' => $data['preferred_time'] ?? null,
                'due_at' => $this->defaultDue($priority),
            ]);

            foreach ($photos as $photo) {
                $this->documents->store($request, $photo, $user, 'photo');
            }

            $this->note($request, $user, 'status', 'Request submitted', false, ['to' => Status::Submitted->value]);
            $this->audit->record('maintenance.submitted', $request, ['priority' => $priority->value], $estate, $user);

            return $request;
        });
    }

    public function transition(MaintenanceRequest $request, Status $to, User $actor, ?string $note = null, array $extra = []): MaintenanceRequest
    {
        $request = DB::transaction(function () use ($request, $to, $actor, $note, $extra) {
            /** @var MaintenanceRequest $locked */
            $locked = MaintenanceRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages(['status' => "A request that is {$from->label()} cannot move to {$to->label()}."]);
            }

            $changes = ['status' => $to] + $extra;
            if ($to === Status::Completed) {
                $changes['completed_at'] = now();
            }
            if ($to === Status::Closed) {
                $changes['closed_at'] = now();
            }
            if ($to === Status::InProgress && $from === Status::Completed) {
                $changes['completed_at'] = null;
            }

            $locked->update($changes);
            $this->note($locked, $actor, 'status', $note ?? "Status changed to {$to->label()}", false, ['from' => $from->value, 'to' => $to->value]);
            $this->audit->record('maintenance.status', $locked, ['from' => $from->value, 'to' => $to->value], $locked->estate_id, $actor);

            return $locked;
        });

        if ($request->requester && $request->requester->isNot($actor)) {
            $request->requester->notify(new MaintenanceUpdated($request, $note ?? "Your request is now {$to->label()}."));
        }

        return $request;
    }

    public function assign(MaintenanceRequest $request, User $actor, ?User $assignee, ?int $vendorId, Priority $priority, ?Carbon $dueAt, ?Carbon $scheduledFor): MaintenanceRequest
    {
        if ($assignee && ! $assignee->hasEstatePermission($request->estate, Permission::MaintenanceWork)) {
            throw ValidationException::withMessages(['assigned_to' => 'This person cannot be assigned maintenance work.']);
        }

        $target = $scheduledFor ? Status::Scheduled : Status::Assigned;
        $extra = ['assigned_to' => $assignee?->id, 'vendor_id' => $vendorId, 'priority' => $priority, 'due_at' => $dueAt, 'scheduled_for' => $scheduledFor];

        if ($request->status === $target) {
            $request->update($extra);
            $this->note($request, $actor, 'assignment', 'Assignment updated', true);
        } else {
            $request = $this->transition($request, $target, $actor, $assignee ? "Assigned to {$assignee->name}" : 'Assigned to vendor', $extra);
        }

        if ($assignee && $assignee->isNot($actor)) {
            $assignee->notify(new MaintenanceUpdated($request, "{$request->title} was assigned to you."));
        }

        return $request;
    }

    public function comment(MaintenanceRequest $request, User $actor, string $body, bool $internal, array $photos = []): MaintenanceUpdate
    {
        $update = $this->note($request, $actor, 'comment', $body, $internal);

        foreach ($photos as $photo) {
            $this->documents->store($request, $photo, $actor, $internal ? 'internal' : 'photo');
        }

        if (! $internal && $request->requester && $request->requester->isNot($actor)) {
            $request->requester->notify(new MaintenanceUpdated($request, $body));
        }

        return $update;
    }

    public function recordCosts(MaintenanceRequest $request, User $actor, int $labour, int $parts): void
    {
        $request->update(['labour_cost' => $labour, 'parts_cost' => $parts]);
        $this->note($request, $actor, 'cost', 'Costs updated', true, ['labour' => $labour, 'parts' => $parts]);
        $this->audit->record('maintenance.costs', $request, ['labour' => $labour, 'parts' => $parts], $request->estate_id, $actor);
    }

    /**
     * Resident confirms the fix (closes) or reports it is not resolved (reopens).
     */
    public function residentResponse(MaintenanceRequest $request, User $resident, bool $resolved, ?string $comment): MaintenanceRequest
    {
        abort_unless($request->requested_by === $resident->id, 403);

        return $this->transition($request, $resolved ? Status::Closed : Status::InProgress, $resident, $resolved ? 'Resident confirmed the work is complete' : 'Resident reported the issue is not resolved: '.$comment);
    }

    private function note(MaintenanceRequest $request, ?User $user, string $type, ?string $body, bool $internal, array $meta = []): MaintenanceUpdate
    {
        return MaintenanceUpdate::create([
            'maintenance_request_id' => $request->id,
            'user_id' => $user?->id,
            'type' => $type,
            'body' => $body,
            'is_internal' => $internal,
            'meta' => $meta ?: null,
        ]);
    }

    private function defaultDue(Priority $priority): Carbon
    {
        return match ($priority) {
            Priority::Urgent => now()->addHours(24),
            Priority::High => now()->addDays(3),
            Priority::Normal => now()->addDays(7),
            Priority::Low => now()->addDays(14),
        };
    }

    private function reference(): string
    {
        do {
            $reference = Reference::make('MR', 7);
        } while (MaintenanceRequest::where('reference', $reference)->exists());

        return $reference;
    }
}
