<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Estate;
use App\Models\User;
use App\Support\EstateContext;
use App\Support\Redactor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function __construct(private EstateContext $context) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $action, ?Model $subject = null, array $properties = [], Estate|int|null $estate = null, ?User $actor = null): AuditLog
    {
        $estateId = match (true) {
            $estate instanceof Estate => $estate->getKey(),
            is_int($estate) => $estate,
            $subject !== null && isset($subject->estate_id) => $subject->estate_id,
            default => $this->context->estateOrNull()?->getKey(),
        };

        return AuditLog::create([
            'estate_id' => $estateId,
            'user_id' => ($actor ?? Auth::user())?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ? Redactor::redact($properties) : null,
            'ip_address' => app()->runningInConsole() ? null : Request::ip(),
            'user_agent' => app()->runningInConsole() ? null : substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
