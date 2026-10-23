<?php

namespace App\Services\Visitors;

use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\Permission;
use App\Enums\VisitStatus;
use App\Models\Estate;
use App\Models\Gate;
use App\Models\PassScan;
use App\Models\Property;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitorPass;
use App\Notifications\VisitorArrivedNotification;
use App\Notifications\WalkInApprovalNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Gate operations. A QR code or numeric code is only a lookup key: every decision is made here,
 * against the database, for an officer with gate permission in this estate.
 */
class GateService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Read-only verification used when a code is scanned or typed.
     */
    public function check(Estate $estate, User $officer, string $input, ?Gate $gate = null): PassCheck
    {
        $this->authorizeOfficer($estate, $officer);

        [$method, $pass] = $this->locate($estate, $input);

        $check = $pass ? $this->evaluate($pass) : PassCheck::fail('not_found', 'No pass matches this code. Check the code or register the visitor as a walk-in.');

        PassScan::create([
            'estate_id' => $estate->id,
            'visitor_pass_id' => $pass?->id,
            'scanned_by' => $officer->id,
            'gate_id' => $gate?->id,
            'method' => $method,
            'result' => $check->result,
        ]);

        return $check;
    }

    /**
     * Atomically consumes one entry on the pass and records the visitor as inside.
     * Two simultaneous admits on a one-time pass cannot both succeed.
     *
     * @param  array{vehicle_plate?: ?string, group_size?: ?int}  $details
     */
    public function admit(VisitorPass $pass, User $officer, ?Gate $gate = null, array $details = []): Visit
    {
        $this->authorizeOfficer($pass->estate, $officer);

        return DB::transaction(function () use ($pass, $officer, $gate, $details) {
            $locked = VisitorPass::whereKey($pass->id)->where('estate_id', $pass->estate_id)->lockForUpdate()->firstOrFail();

            $check = $this->evaluate($locked);
            if (! $check->valid) {
                throw ValidationException::withMessages(['pass' => $check->message]);
            }

            // Conditional increment: the WHERE clause is the final guard against double entry.
            $affected = VisitorPass::whereKey($locked->id)
                ->where('status', PassStatus::Active->value)
                ->where(fn ($q) => $q->whereNull('max_entries')->orWhereColumn('entries_count', '<', 'max_entries'))
                ->increment('entries_count');

            if ($affected !== 1) {
                throw ValidationException::withMessages(['pass' => 'This pass has just been used. Entry not recorded.']);
            }

            $locked->refresh();

            if ($locked->max_entries !== null && $locked->entries_count >= $locked->max_entries) {
                $locked->update(['status' => PassStatus::Consumed, 'code_lookup' => null]);
            }

            $visit = Visit::create([
                'estate_id' => $locked->estate_id,
                'visitor_pass_id' => $locked->id,
                'property_id' => $locked->property_id,
                'host_user_id' => $locked->host_user_id,
                'gate_id' => $gate?->id,
                'source' => 'pass',
                'status' => VisitStatus::Inside,
                'visitor_name' => $locked->visitor_name,
                'visitor_phone' => $locked->visitor_phone,
                'category' => $locked->category,
                'group_size' => $details['group_size'] ?? $locked->group_size,
                'vehicle_plate' => isset($details['vehicle_plate']) ? strtoupper($details['vehicle_plate']) : $locked->vehicle_plate,
                'checked_in_at' => now(),
                'checked_in_by' => $officer->id,
                'recorded_by' => $officer->id,
            ]);

            PassScan::create(['estate_id' => $locked->estate_id, 'visitor_pass_id' => $locked->id, 'scanned_by' => $officer->id, 'gate_id' => $gate?->id, 'method' => 'admit', 'result' => 'admitted']);
            $this->audit->record('visit.admitted', $visit, ['pass_id' => $locked->id], $locked->estate_id, $officer);

            DB::afterCommit(fn () => $locked->host?->notify(new VisitorArrivedNotification($visit)));

            return $visit;
        });
    }

    public function deny(Estate $estate, User $officer, string $reason, ?VisitorPass $pass = null, array $details = []): Visit
    {
        $this->authorizeOfficer($estate, $officer);

        $visit = Visit::create([
            'estate_id' => $estate->id,
            'visitor_pass_id' => $pass?->id,
            'property_id' => $pass?->property_id ?? ($details['property_id'] ?? null),
            'host_user_id' => $pass?->host_user_id,
            'source' => $pass ? 'pass' : 'walk_in',
            'status' => VisitStatus::Denied,
            'visitor_name' => $pass?->visitor_name ?? ($details['visitor_name'] ?? 'Unknown visitor'),
            'visitor_phone' => $pass?->visitor_phone ?? ($details['visitor_phone'] ?? null),
            'category' => $pass?->category ?? ($details['category'] ?? 'guest'),
            'denial_reason' => $reason,
            'recorded_by' => $officer->id,
        ]);

        $this->audit->record('visit.denied', $visit, ['reason' => $reason], $estate, $officer);

        return $visit;
    }

    /**
     * @param  array{property_id: int, visitor_name: string, visitor_phone?: ?string, category: string, purpose?: ?string, vehicle_plate?: ?string, group_size?: ?int}  $data
     */
    public function registerWalkIn(Estate $estate, User $officer, array $data, ?Gate $gate = null): Visit
    {
        $this->authorizeOfficer($estate, $officer);

        $property = Property::where('estate_id', $estate->id)->findOrFail($data['property_id']);
        $hosts = User::whereHas('occupancies', fn ($q) => $q->where('property_id', $property->id)->where('status', 'active'))->where('status', 'active')->get();
        $needsApproval = (bool) $estate->setting('visitors.require_walk_in_approval') && $hosts->isNotEmpty();

        $visit = Visit::create([
            'estate_id' => $estate->id,
            'property_id' => $property->id,
            'gate_id' => $gate?->id,
            'source' => 'walk_in',
            'status' => $needsApproval ? VisitStatus::AwaitingHost : VisitStatus::Inside,
            'visitor_name' => $data['visitor_name'],
            'visitor_phone' => $data['visitor_phone'] ?? null,
            'category' => $data['category'],
            'purpose' => $data['purpose'] ?? null,
            'group_size' => $data['group_size'] ?? 1,
            'vehicle_plate' => isset($data['vehicle_plate']) ? strtoupper($data['vehicle_plate']) : null,
            'checked_in_at' => $needsApproval ? null : now(),
            'checked_in_by' => $needsApproval ? null : $officer->id,
            'recorded_by' => $officer->id,
        ]);

        $this->audit->record('visit.walk_in', $visit, ['property' => $property->identifier, 'awaiting_host' => $needsApproval], $estate, $officer);

        if ($needsApproval) {
            Notification::send($hosts, new WalkInApprovalNotification($visit));
        }

        return $visit;
    }

    /**
     * A resident linked to the visit's property approves or declines a walk-in.
     */
    public function respondToWalkIn(Visit $visit, User $host, bool $approve): Visit
    {
        return DB::transaction(function () use ($visit, $host, $approve) {
            $locked = Visit::whereKey($visit->id)->lockForUpdate()->firstOrFail();

            $linked = $host->authorizedProperties($locked->estate_id)->whereKey($locked->property_id)->exists();
            abort_unless($linked, 403);

            if ($locked->status !== VisitStatus::AwaitingHost) {
                throw ValidationException::withMessages(['visit' => 'This visitor has already been handled at the gate.']);
            }

            $locked->update([
                'status' => $approve ? VisitStatus::Inside : VisitStatus::Declined,
                'host_user_id' => $host->id,
                'host_responded_at' => now(),
                'checked_in_at' => $approve ? now() : null,
                'checked_in_by' => $approve ? $locked->recorded_by : null,
            ]);

            $this->audit->record($approve ? 'visit.host_approved' : 'visit.host_declined', $locked, [], $locked->estate_id, $host);

            return $locked;
        });
    }

    public function admitAwaiting(Visit $visit, User $officer): Visit
    {
        $this->authorizeOfficer($visit->estate, $officer);
        abort_unless($visit->status === VisitStatus::AwaitingHost, 409, 'Visitor is no longer awaiting approval.');
        abort_unless($officer->hasEstatePermission($visit->estate, Permission::GateSupervise), 403, 'Only a security supervisor can override host approval.');

        $visit->update(['status' => VisitStatus::Inside, 'checked_in_at' => now(), 'checked_in_by' => $officer->id]);
        $this->audit->record('visit.supervisor_override', $visit, [], $visit->estate_id, $officer);

        return $visit;
    }

    public function checkOut(Visit $visit, User $officer): Visit
    {
        $this->authorizeOfficer($visit->estate, $officer);

        $affected = Visit::whereKey($visit->id)->where('status', VisitStatus::Inside->value)
            ->update(['status' => VisitStatus::Exited->value, 'checked_out_at' => now(), 'checked_out_by' => $officer->id, 'updated_at' => now()]);

        if ($affected !== 1) {
            throw ValidationException::withMessages(['visit' => 'This visitor is not currently checked in.']);
        }

        $this->audit->record('visit.checked_out', $visit, [], $visit->estate_id, $officer);

        return $visit->refresh();
    }

    public function evaluate(VisitorPass $pass, ?Carbon $now = null): PassCheck
    {
        $now ??= now();

        if ($pass->status === PassStatus::Revoked) {
            return PassCheck::fail('revoked', 'This pass was cancelled by the host.', $pass);
        }

        if ($pass->status === PassStatus::Consumed) {
            return PassCheck::fail('consumed', 'This pass has already been used.', $pass);
        }

        if ($pass->status === PassStatus::Expired || $pass->valid_until->lt($now)) {
            return PassCheck::fail('expired', 'This pass has expired.', $pass);
        }

        if ($pass->valid_from->gt($now)) {
            return PassCheck::fail('not_yet_valid', 'This pass is not valid until '.$pass->valid_from->timezone($pass->estate->timezone())->format('D j M, g:ia').'.', $pass);
        }

        if ($pass->max_entries !== null && $pass->entries_count >= $pass->max_entries) {
            return PassCheck::fail('consumed', 'This pass has no entries left.', $pass);
        }

        if ($pass->pass_type === PassType::Recurring && $pass->recurrence) {
            $local = $now->copy()->timezone($pass->estate->timezone());
            $days = $pass->recurrence['days'] ?? [];
            $time = $local->format('H:i');

            if (! in_array($local->dayOfWeekIso, $days, true) || $time < $pass->recurrence['start_time'] || $time > $pass->recurrence['end_time']) {
                return PassCheck::fail('outside_schedule', 'This recurring pass is not valid at this time of day.', $pass);
            }
        }

        if ($pass->host?->status !== 'active' || ! $pass->host?->authorizedProperties($pass->estate_id)->whereKey($pass->property_id)->exists()) {
            return PassCheck::fail('host_inactive', 'The resident who issued this pass is no longer linked to the property.', $pass);
        }

        return PassCheck::ok($pass);
    }

    /**
     * @return array{0: string, 1: ?VisitorPass}
     */
    private function locate(Estate $estate, string $input): array
    {
        $input = trim($input);

        if (str_starts_with($input, 'PNE:')) {
            $token = substr($input, 4);

            return ['qr', VisitorPass::with('estate', 'property', 'host')->where('estate_id', $estate->id)->where('token_lookup', PassService::tokenLookup($token))->first()];
        }

        $digits = preg_replace('/\D/', '', $input);

        if (strlen($digits) === 6) {
            return ['code', VisitorPass::with('estate', 'property', 'host')->where('estate_id', $estate->id)->where('code_lookup', PassService::codeLookup($estate->id, $digits))->first()];
        }

        return ['code', null];
    }

    private function authorizeOfficer(Estate $estate, User $officer): void
    {
        abort_unless($officer->hasEstatePermission($estate, Permission::GateOperate), 403);
    }
}
