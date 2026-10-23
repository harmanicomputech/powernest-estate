<?php

namespace App\Services\Visitors;

use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Models\Estate;
use App\Models\User;
use App\Models\VisitorPass;
use App\Services\Audit\AuditLogger;
use App\Support\Reference;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PassService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{property_id: int, visitor_name: string, visitor_phone?: ?string, visitor_email?: ?string, category: string, pass_type: string, valid_from: string, valid_until: string, group_size?: ?int, vehicle_plate?: ?string, notes?: ?string, max_entries?: ?int, days?: ?array<int, int|string>, start_time?: ?string, end_time?: ?string}  $data
     */
    public function issue(User $host, Estate $estate, array $data): VisitorPass
    {
        $property = $host->authorizedProperties($estate)->whereKey($data['property_id'])->first();

        if (! $property) {
            throw ValidationException::withMessages(['property_id' => 'You can only invite visitors to a property you are linked to.']);
        }

        $categories = $estate->setting('visitors.pass_categories');
        if (! in_array($data['category'], $categories, true)) {
            throw ValidationException::withMessages(['category' => 'This visit category is not enabled for your estate.']);
        }

        $tz = $estate->timezone();
        $from = Carbon::parse($data['valid_from'], $tz)->utc();
        $until = Carbon::parse($data['valid_until'], $tz)->utc();
        $type = PassType::from($data['pass_type']);

        if ($until->lte($from)) {
            throw ValidationException::withMessages(['valid_until' => 'The pass must end after it starts.']);
        }

        if ($until->lt(now())) {
            throw ValidationException::withMessages(['valid_until' => 'The pass would already be expired.']);
        }

        $maxDays = (int) $estate->setting('visitors.max_pass_days');
        if ($from->diffInDays($until) > $maxDays) {
            throw ValidationException::withMessages(['valid_until' => "Passes can be valid for at most {$maxDays} days."]);
        }

        $recurrence = null;
        if ($type === PassType::Recurring) {
            if (empty($data['days']) || empty($data['start_time']) || empty($data['end_time'])) {
                throw ValidationException::withMessages(['days' => 'Choose the days and hours this recurring pass is valid.']);
            }
            $recurrence = [
                'days' => array_values(array_map('intval', $data['days'])),
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
            ];
        }

        $maxEntries = match ($type) {
            PassType::OneTime => 1,
            PassType::MultiEntry => $data['max_entries'] ?? null,
            PassType::Recurring => null,
        };

        for ($attempt = 1; ; $attempt++) {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $code = Reference::numericCode(6);

            try {
                $pass = VisitorPass::create([
                    'estate_id' => $estate->id,
                    'property_id' => $property->id,
                    'host_user_id' => $host->id,
                    'token_lookup' => self::tokenLookup($token),
                    'token_encrypted' => $token,
                    'code_lookup' => self::codeLookup($estate->id, $code),
                    'code_encrypted' => $code,
                    'visitor_name' => $data['visitor_name'],
                    'visitor_phone' => $data['visitor_phone'] ?? null,
                    'visitor_email' => $data['visitor_email'] ?? null,
                    'category' => $data['category'],
                    'pass_type' => $type,
                    'group_size' => $data['group_size'] ?? 1,
                    'vehicle_plate' => isset($data['vehicle_plate']) ? strtoupper($data['vehicle_plate']) : null,
                    'valid_from' => $from,
                    'valid_until' => $until,
                    'recurrence' => $recurrence,
                    'max_entries' => $maxEntries,
                    'status' => PassStatus::Active,
                    'notes' => $data['notes'] ?? null,
                ]);
                break;
            } catch (QueryException $e) {
                // Unique collision on the numeric code (or token). Retry with fresh values.
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }

        $this->audit->record('visitor_pass.issued', $pass, ['category' => $pass->category, 'type' => $type->value, 'property' => $property->identifier], $estate, $host);

        return $pass;
    }

    public function revoke(VisitorPass $pass, User $actor): void
    {
        DB::transaction(function () use ($pass, $actor) {
            $locked = VisitorPass::whereKey($pass->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PassStatus::Active) {
                throw ValidationException::withMessages(['pass' => 'Only active passes can be revoked.']);
            }

            $locked->update(['status' => PassStatus::Revoked, 'revoked_at' => now(), 'revoked_by' => $actor->id, 'code_lookup' => null]);
            $this->audit->record('visitor_pass.revoked', $locked, [], $locked->estate_id, $actor);
        });
    }

    /**
     * Marks active passes past their window as expired and frees their numeric codes.
     */
    public function expireStale(): int
    {
        return VisitorPass::where('status', PassStatus::Active->value)->where('valid_until', '<', now())
            ->update(['status' => PassStatus::Expired->value, 'code_lookup' => null, 'updated_at' => now()]);
    }

    public static function tokenLookup(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function codeLookup(int $estateId, string $code): string
    {
        return hash_hmac('sha256', $estateId.':'.$code, (string) config('app.key'));
    }

    public static function qrPayload(VisitorPass $pass): string
    {
        return 'PNE:'.$pass->token();
    }
}
