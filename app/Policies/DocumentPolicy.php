<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        $owner = $document->documentable;

        return match (true) {
            $owner instanceof MaintenanceRequest => $document->category === 'internal' ? Gate::forUser($user)->allows('viewInternal', $owner) : Gate::forUser($user)->allows('view', $owner),
            $owner instanceof Incident => Gate::forUser($user)->allows('view', $owner),
            default => false,
        };
    }
}
