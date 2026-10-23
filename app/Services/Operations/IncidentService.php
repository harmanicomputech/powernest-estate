<?php

namespace App\Services\Operations;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\Permission;
use App\Models\Estate;
use App\Models\Incident;
use App\Models\IncidentUpdate;
use App\Models\User;
use App\Notifications\IncidentAssigned;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentStore;
use App\Services\Estate\MembershipService;
use App\Support\Reference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class IncidentService
{
    public function __construct(private AuditLogger $audit, private DocumentStore $documents, private MembershipService $members) {}

    public function report(User $user, Estate $estate, array $data, array $evidence = []): Incident
    {
        $incident = DB::transaction(function () use ($user, $estate, $data, $evidence) {
            $incident = Incident::create([
                'estate_id' => $estate->id,
                'reference' => $this->reference(),
                'reported_by' => $user->id,
                'property_id' => $data['property_id'] ?? null,
                'category' => $data['category'],
                'severity' => IncidentSeverity::from($data['severity']),
                'status' => IncidentStatus::Open,
                'title' => $data['title'],
                'description' => $data['description'],
                'location' => $data['location'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);

            foreach ($evidence as $file) {
                $this->documents->store($incident, $file, $user, 'evidence');
            }

            $this->update($incident, $user, 'note', 'Incident reported');
            $this->audit->record('incident.reported', $incident, ['severity' => $incident->severity->value], $estate, $user);

            return $incident;
        });

        if (in_array($incident->severity, [IncidentSeverity::High, IncidentSeverity::Critical], true)) {
            Notification::send($this->members->usersWithPermission($estate, Permission::IncidentsManage), new IncidentAssigned($incident));
        }

        return $incident;
    }

    public function assign(Incident $incident, User $actor, User $assignee): void
    {
        if (! $assignee->hasEstatePermission($incident->estate, Permission::IncidentsReport) || ! $assignee->rolesIn($incident->estate)->contains(fn ($r) => $r->isStaff())) {
            throw ValidationException::withMessages(['assigned_to' => 'Incidents can only be assigned to estate staff.']);
        }

        $incident->update(['assigned_to' => $assignee->id, 'status' => $incident->status === IncidentStatus::Open ? IncidentStatus::Investigating : $incident->status]);
        $this->update($incident, $actor, 'assignment', "Assigned to {$assignee->name}");
        $this->audit->record('incident.assigned', $incident, ['assignee' => $assignee->id], $incident->estate_id, $actor);
        $assignee->notify(new IncidentAssigned($incident));
    }

    public function setStatus(Incident $incident, User $actor, IncidentStatus $status, ?string $note): void
    {
        $changes = ['status' => $status];
        if ($status === IncidentStatus::Resolved) {
            $changes += ['resolved_at' => now(), 'resolution' => $note];
        }
        if ($status === IncidentStatus::Closed) {
            $changes['closed_at'] = now();
        }

        $incident->update($changes);
        $this->update($incident, $actor, 'status', $note ?: "Status changed to {$status->label()}", ['to' => $status->value]);
        $this->audit->record('incident.status', $incident, ['to' => $status->value], $incident->estate_id, $actor);
    }

    public function update(Incident $incident, ?User $user, string $type, ?string $body, array $meta = []): IncidentUpdate
    {
        return $incident->updates()->create(['user_id' => $user?->id, 'type' => $type, 'body' => $body, 'meta' => $meta ?: null]);
    }

    private function reference(): string
    {
        do {
            $reference = Reference::make('IR', 7);
        } while (Incident::where('reference', $reference)->exists());

        return $reference;
    }
}
