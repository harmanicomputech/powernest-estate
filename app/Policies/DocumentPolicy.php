<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Document;
use App\Models\Incident;
use App\Models\MaintenanceRequest;
use App\Models\Property;
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
            $owner instanceof Property => $user->hasEstatePermission($owner->estate, Permission::PropertiesView)
                || ($document->category === 'property_shared' && $user->authorizedProperties($owner->estate_id)->whereKey($owner->id)->exists()),
            default => false,
        };
    }

    public function delete(User $user, Document $document): bool
    {
        return $document->documentable instanceof Property && $user->hasEstatePermission($document->estate, Permission::PropertiesManage);
    }
}
