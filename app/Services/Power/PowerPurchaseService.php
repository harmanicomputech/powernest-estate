<?php

namespace App\Services\Power;

use App\Enums\Permission;
use App\Enums\PowerPurchaseStatus as Status;
use App\Integrations\IntegrationException;
use App\Integrations\Payments\CheckoutSession;
use App\Integrations\Payments\GatewayManager;
use App\Integrations\Payments\PaymentIntent;
use App\Integrations\Payments\PaymentVerification;
use App\Integrations\Payments\VerificationStatus;
use App\Integrations\Vending\ElectricityVendor;
use App\Integrations\Vending\VendOutcome;
use App\Jobs\VendElectricity;
use App\Models\Estate;
use App\Models\Meter;
use App\Models\PowerPurchase;
use App\Models\PowerPurchaseEvent;
use App\Models\PowerToken;
use App\Models\User;
use App\Models\VendAttempt;
use App\Notifications\PowerPurchaseNeedsAttention;
use App\Notifications\PowerTokenIssued;
use App\Services\Audit\AuditLogger;
use App\Services\Estate\MembershipService;
use App\Support\Money;
use App\Support\Reference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Owns every state change of an electricity purchase:
 *
 *   pending_payment → paid → vending → token_issued
 *                         ↘ vend_failed / reconciliation_required → (manual) vending | token_issued | refunded
 *
 * Rules: the browser never decides amount or status; payment is only confirmed from a server-side
 * verification; a token is only requested after confirmation; one purchase can hold at most one
 * token (unique index); ambiguous vend outcomes are never retried automatically.
 */
class PowerPurchaseService
{
    public function __construct(
        private GatewayManager $gateways,
        private ElectricityVendor $vendor,
        private AuditLogger $audit,
    ) {}

    public function initiate(User $user, Estate $estate, Meter $meter, int $vendAmount, string $gateway): PowerPurchase
    {
        abort_unless($user->hasEstatePermission($estate, Permission::PowerPurchase), 403);

        $authorized = $meter->estate_id === $estate->id
            && $meter->status === 'active'
            && $user->authorizedProperties($estate)->whereKey($meter->property_id)->exists();

        if (! $authorized) {
            throw ValidationException::withMessages(['meter_id' => 'Choose a meter registered to your property.']);
        }

        $min = (int) $estate->setting('power.min_amount');
        $max = (int) $estate->setting('power.max_amount');

        if ($vendAmount < $min || $vendAmount > $max) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount between '.Money::format($min, showKobo: false).' and '.Money::format($max, showKobo: false).'.']);
        }

        if (! array_key_exists($gateway, $this->gateways->availableFor($estate))) {
            throw ValidationException::withMessages(['gateway' => 'This payment method is not available.']);
        }

        $serviceCharge = (int) $estate->setting('power.service_charge');

        return DB::transaction(function () use ($user, $estate, $meter, $vendAmount, $serviceCharge, $gateway) {
            $purchase = PowerPurchase::create([
                'reference' => $this->uniqueReference(),
                'estate_id' => $estate->id,
                'user_id' => $user->id,
                'meter_id' => $meter->id,
                'property_id' => $meter->property_id,
                'meter_number' => $meter->meter_number,
                'gateway' => $gateway,
                'currency' => $estate->currency ?? 'NGN',
                'vend_amount' => $vendAmount,
                'service_charge' => $serviceCharge,
                'total_amount' => $vendAmount + $serviceCharge,
                'status' => Status::PendingPayment,
            ]);

            $this->logEvent($purchase, null, Status::PendingPayment, $user, 'Purchase created');
            $this->audit->record('power.purchase_created', $purchase, ['total' => $purchase->total_amount, 'gateway' => $gateway], $estate, $user);

            return $purchase;
        });
    }

    public function checkout(PowerPurchase $purchase): CheckoutSession
    {
        if ($purchase->status !== Status::PendingPayment) {
            throw ValidationException::withMessages(['purchase' => 'This purchase is no longer awaiting payment.']);
        }

        $user = $purchase->user;

        return $this->gateways->get($purchase->gateway)->initialize(new PaymentIntent(
            reference: $purchase->reference,
            amount: $purchase->total_amount,
            currency: $purchase->currency,
            email: $user->email,
            customerName: $user->name,
            phone: $user->phone,
            callbackUrl: route('power.purchases.return', $purchase),
            description: "Electricity for meter {$purchase->meter_number}",
            metadata: ['purpose' => 'electricity', 'reference' => $purchase->reference],
        ));
    }

    /**
     * Ask the gateway for the payment status and apply it. Safe to call any number of times.
     */
    public function verifyWithGateway(PowerPurchase $purchase, string $via): PowerPurchase
    {
        if ($purchase->status->isPaymentConfirmed()) {
            return $purchase;
        }

        try {
            $verification = $this->gateways->get($purchase->gateway)->verify($purchase->reference);
        } catch (IntegrationException $e) {
            Log::warning('Payment verification unavailable', ['reference' => $purchase->reference, 'gateway' => $purchase->gateway, 'error' => $e->getMessage()]);
            $purchase->forceFill(['last_verified_at' => now()])->save();

            return $purchase;
        }

        return $this->applyVerification($purchase, $verification, $via);
    }

    public function applyVerification(PowerPurchase $purchase, PaymentVerification $verification, string $via, ?User $actor = null): PowerPurchase
    {
        $confirmed = false;

        $purchase = DB::transaction(function () use ($purchase, $verification, $via, $actor, &$confirmed) {
            /** @var PowerPurchase $locked */
            $locked = PowerPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $locked->last_verified_at = now();

            if ($verification->reference !== $locked->reference || $verification->gateway !== $locked->gateway) {
                Log::warning('Verification reference mismatch', ['expected' => $locked->reference, 'got' => $verification->reference]);
                $locked->save();

                return $locked;
            }

            // Idempotency: once money is confirmed, repeated callbacks / webhooks change nothing.
            if ($locked->status->isPaymentConfirmed()) {
                $locked->save();

                return $locked;
            }

            if ($verification->status === VerificationStatus::Failed && $locked->status === Status::PendingPayment) {
                $locked->failure_code = 'payment_failed';
                $locked->failure_reason = mb_substr((string) ($verification->message ?? 'The payment was not completed.'), 0, 250);
                $this->transition($locked, Status::PaymentFailed, $actor, 'Gateway reported payment failed');

                return $locked;
            }

            if (! $verification->succeeded()) {
                $locked->save();

                return $locked;
            }

            $locked->amount_paid = $verification->amount;
            $locked->gateway_reference = $verification->gatewayReference;
            $locked->payment_channel = $verification->channel;
            $locked->paid_at = $verification->paidAt ?? now();
            $locked->payment_confirmed_via = $via;

            if ($verification->currency !== null && $verification->currency !== $locked->currency) {
                $locked->failure_code = 'currency_mismatch';
                $locked->failure_reason = "Paid in {$verification->currency}, expected {$locked->currency}.";
                $this->transition($locked, Status::ReconciliationRequired, $actor, 'Currency mismatch');

                return $locked;
            }

            if ($verification->amount === null || $verification->amount < $locked->total_amount) {
                $locked->failure_code = 'amount_mismatch';
                $locked->failure_reason = 'Received '.Money::format($verification->amount).', expected '.Money::format($locked->total_amount).'.';
                $this->transition($locked, Status::ReconciliationRequired, $actor, 'Amount mismatch');

                return $locked;
            }

            $locked->failure_code = null;
            $locked->failure_reason = null;
            $this->transition($locked, Status::Paid, $actor, "Payment confirmed via {$via}");
            $this->audit->record('power.payment_confirmed', $locked, ['via' => $via, 'amount' => $verification->amount], $locked->estate_id, $actor);
            $confirmed = true;

            return $locked;
        });

        if ($confirmed) {
            VendElectricity::dispatch($purchase->id);
        } elseif ($purchase->status === Status::ReconciliationRequired) {
            $this->notifyStaff($purchase);
        }

        return $purchase;
    }

    /**
     * Request the token from the vendor. Only runs from "paid", or from a failed / unresolved state
     * when an authorized staff member explicitly confirmed that no token was issued.
     */
    public function vend(PowerPurchase $purchase, ?User $actor = null): PowerPurchase
    {
        $attempt = DB::transaction(function () use ($purchase, $actor) {
            /** @var PowerPurchase $locked */
            $locked = PowerPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            $allowed = $locked->status === Status::Paid
                || ($actor !== null && in_array($locked->status, [Status::VendFailed, Status::ReconciliationRequired], true) && $locked->amount_paid >= $locked->total_amount);

            if (! $allowed || $locked->token()->exists()) {
                return null;
            }

            $locked->vend_attempts++;
            $locked->last_vend_attempt_at = now();
            $this->transition($locked, Status::Vending, $actor, $actor ? 'Manual vend retry' : 'Vending started');

            return VendAttempt::create([
                'power_purchase_id' => $locked->id,
                'attempt' => $locked->vend_attempts,
                'initiated_by' => $actor?->id,
                'started_at' => now(),
            ]);
        });

        if (! $attempt) {
            return $purchase->refresh();
        }

        // On shared hosting vending can run inside a web request; give the vendor call room to finish.
        @set_time_limit(max(120, (int) config('services.stron.timeout') + 30));
        $result = $this->vendor->vend($purchase->meter_number, $purchase->vend_amount, $purchase->reference);

        $notifyStaff = false;
        $issued = DB::transaction(function () use ($purchase, $attempt, $result, $actor, &$notifyStaff) {
            /** @var PowerPurchase $locked */
            $locked = PowerPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            $attempt->update([
                'outcome' => $result->outcome->value,
                'http_status' => $result->httpStatus,
                'error' => $result->error,
                'response' => $result->raw ?: null,
                'finished_at' => now(),
            ]);

            if ($locked->status !== Status::Vending) {
                // Someone resolved it while the request was in flight; record the attempt only.
                return false;
            }

            switch ($result->outcome) {
                case VendOutcome::Success:
                    PowerToken::create([
                        'power_purchase_id' => $locked->id,
                        'token' => $result->token,
                        'units' => $result->units,
                        'unit_label' => $result->unitLabel ?? 'kWh',
                        'meter_number' => $locked->meter_number,
                        'source' => 'vendor',
                        'meta' => $result->raw ?: null,
                        'issued_at' => now(),
                    ]);
                    $locked->token_issued_at = now();
                    $locked->failure_code = null;
                    $locked->failure_reason = null;
                    $this->transition($locked, Status::TokenIssued, $actor, 'Token issued');

                    return true;

                case VendOutcome::NotSent:
                    $locked->failure_reason = $result->error;
                    $this->transition($locked, Status::Paid, $actor, 'Vendor unreachable; request not delivered');

                    return false;

                case VendOutcome::Failed:
                    $locked->failure_code = 'vend_failed';
                    $locked->failure_reason = $result->error;
                    $this->transition($locked, Status::VendFailed, $actor, 'Vendor declined');
                    $notifyStaff = true;

                    return false;

                case VendOutcome::Unknown:
                    $locked->failure_code = 'vend_unknown';
                    $locked->failure_reason = 'The vending service did not confirm the result. Check the Stron portal before retrying.';
                    $this->transition($locked, Status::ReconciliationRequired, $actor, 'Vend outcome unknown');
                    $notifyStaff = true;

                    return false;
            }

            throw new LogicException('Unhandled vend outcome.');
        });

        $purchase->refresh();

        if ($issued) {
            $this->audit->record('power.token_issued', $purchase, ['attempt' => $attempt->attempt], $purchase->estate_id, $actor);
            $purchase->user->notify(new PowerTokenIssued($purchase));
        } elseif ($purchase->status === Status::Paid && $purchase->vend_attempts < (int) config('powernest.power.max_auto_vend_attempts')) {
            VendElectricity::dispatch($purchase->id)->delay(now()->addSeconds(30 * $purchase->vend_attempts));
        } elseif ($purchase->status === Status::Paid) {
            DB::transaction(function () use ($purchase) {
                $locked = PowerPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === Status::Paid) {
                    $locked->failure_code = 'vendor_unreachable';
                    $this->transition($locked, Status::Vending, null, 'Auto-retries exhausted');
                    $this->transition($locked, Status::VendFailed, null, 'Vendor unreachable after retries');
                }
            });
            $notifyStaff = true;
        }

        if ($notifyStaff) {
            $this->notifyStaff($purchase->refresh());
        }

        return $purchase->refresh();
    }

    public function retryVend(PowerPurchase $purchase, User $actor, string $note): PowerPurchase
    {
        $this->authorizeReconcile($purchase, $actor);

        if (! in_array($purchase->status, [Status::VendFailed, Status::ReconciliationRequired], true)) {
            throw ValidationException::withMessages(['purchase' => 'Only failed or unresolved purchases can be retried.']);
        }

        if (($purchase->amount_paid ?? 0) < $purchase->total_amount) {
            throw ValidationException::withMessages(['purchase' => 'Payment has not been confirmed in full. Resolve the payment before vending.']);
        }

        $this->audit->record('power.vend_retry_requested', $purchase, ['note' => $note], $purchase->estate_id, $actor);

        return $this->vend($purchase, $actor);
    }

    public function recordManualToken(PowerPurchase $purchase, User $actor, string $token, ?string $units, string $note): PowerPurchase
    {
        $this->authorizeReconcile($purchase, $actor);

        $issued = DB::transaction(function () use ($purchase, $actor, $token, $units, $note) {
            $locked = PowerPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [Status::VendFailed, Status::ReconciliationRequired], true) || $locked->token()->exists()) {
                throw ValidationException::withMessages(['token' => 'A token can only be recorded for a failed or unresolved purchase without a token.']);
            }

            PowerToken::create([
                'power_purchase_id' => $locked->id,
                'token' => preg_replace('/\s+/', '', $token),
                'units' => $units,
                'meter_number' => $locked->meter_number,
                'source' => 'manual',
                'recorded_by' => $actor->id,
                'meta' => ['note' => $note],
                'issued_at' => now(),
            ]);

            $locked->token_issued_at = now();
            $this->transition($locked, Status::TokenIssued, $actor, 'Token recorded manually: '.$note);
            $this->audit->record('power.token_recorded_manually', $locked, ['note' => $note], $locked->estate_id, $actor);

            return $locked;
        });

        $issued->user->notify(new PowerTokenIssued($issued));

        return $issued;
    }

    /**
     * Estate staff issue a token without payment (e.g. compensation, prepaid at the office). The token is
     * vended immediately through the normal vending path and emailed to the meter's main occupant.
     */
    public function issueWithoutPayment(Estate $estate, User $actor, Meter $meter, int $vendAmount, string $note): PowerPurchase
    {
        abort_unless($actor->hasEstatePermission($estate, Permission::PowerVendManual), 403);
        abort_unless($meter->estate_id === $estate->id, 404);

        $max = (int) $estate->setting('power.max_amount');
        if ($vendAmount < 10_000 || $vendAmount > $max) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount between ₦100 and '.Money::format($max, showKobo: false).'.']);
        }

        $owner = $meter->property?->occupancies()->where('status', 'active')->orderByDesc('is_primary')->with('user')->first()?->user;

        $purchase = DB::transaction(function () use ($estate, $actor, $meter, $vendAmount, $note, $owner) {
            $purchase = PowerPurchase::create([
                'reference' => $this->uniqueReference(),
                'estate_id' => $estate->id,
                'user_id' => ($owner ?? $actor)->id,
                'issued_by' => $actor->id,
                'meter_id' => $meter->id,
                'property_id' => $meter->property_id,
                'meter_number' => $meter->meter_number,
                'gateway' => 'office',
                'currency' => $estate->currency ?? 'NGN',
                'vend_amount' => $vendAmount,
                'service_charge' => 0,
                'total_amount' => $vendAmount,
                'amount_paid' => 0,
                'payment_confirmed_via' => 'staff',
                'paid_at' => now(),
                'office_note' => mb_substr($note, 0, 250),
                'status' => Status::PendingPayment,
            ]);

            $this->logEvent($purchase, null, Status::PendingPayment, $actor, 'Issued by estate office: '.$note);
            $this->transition($purchase, Status::Paid, $actor, 'No payment required (estate office)');
            $this->audit->record('power.office_vend', $purchase, ['amount' => $vendAmount, 'note' => $note, 'owner' => $owner?->id], $estate, $actor);

            return $purchase;
        });

        return $this->vend($purchase, $actor);
    }

    public function confirmPaymentManually(PowerPurchase $purchase, User $actor, int $amountReceived, string $evidence): PowerPurchase
    {
        $this->authorizeReconcile($purchase, $actor);
        abort_unless($actor->hasEstatePermission($purchase->estate, Permission::BillingApprove), 403, 'Only a finance approver can confirm payments manually.');

        $eligible = in_array($purchase->status, [Status::PendingPayment, Status::Expired, Status::PaymentFailed], true)
            || ($purchase->status === Status::ReconciliationRequired && in_array($purchase->failure_code, ['amount_mismatch', 'currency_mismatch'], true));

        if (! $eligible) {
            throw ValidationException::withMessages(['purchase' => 'Payment for this purchase is already confirmed or cannot be confirmed manually.']);
        }

        $updated = DB::transaction(function () use ($purchase, $actor, $amountReceived, $evidence) {
            $locked = PowerPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($amountReceived < $locked->total_amount) {
                throw ValidationException::withMessages(['amount_received' => 'The amount received is less than the purchase total. Refund or contact the customer instead.']);
            }

            $locked->amount_paid = $amountReceived;
            $locked->paid_at ??= now();
            $locked->payment_confirmed_via = 'manual';
            $locked->failure_code = null;
            $locked->failure_reason = null;

            if ($locked->status === Status::ReconciliationRequired) {
                // Was an amount/currency issue: move straight to vending through the normal path.
                $locked->save();
                $this->logEvent($locked, Status::ReconciliationRequired, Status::ReconciliationRequired, $actor, 'Payment confirmed manually: '.$evidence);
            } else {
                $this->transition($locked, Status::Paid, $actor, 'Payment confirmed manually: '.$evidence);
            }

            $this->audit->record('power.payment_confirmed_manually', $locked, ['amount' => $amountReceived, 'evidence' => $evidence], $locked->estate_id, $actor);

            return $locked;
        });

        return $updated->status === Status::Paid ? tap($updated, fn () => VendElectricity::dispatch($updated->id)) : $this->vend($updated, $actor);
    }

    public function markRefunded(PowerPurchase $purchase, User $actor, string $note): PowerPurchase
    {
        $this->authorizeReconcile($purchase, $actor);

        return DB::transaction(function () use ($purchase, $actor, $note) {
            $locked = PowerPurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo(Status::Refunded)) {
                throw ValidationException::withMessages(['purchase' => 'This purchase cannot be marked as refunded.']);
            }

            $this->transition($locked, Status::Refunded, $actor, 'Refunded: '.$note);
            $this->audit->record('power.refunded', $locked, ['note' => $note], $locked->estate_id, $actor);

            return $locked;
        });
    }

    /**
     * Scheduled sweep: re-verify recent unpaid purchases, expire abandoned ones, resume paid ones,
     * and flag purchases stuck mid-vend (process crash) for human review.
     *
     * @return array{verified: int, expired: int, resumed: int, flagged: int}
     */
    public function sweep(): array
    {
        $stats = ['verified' => 0, 'expired' => 0, 'resumed' => 0, 'flagged' => 0];
        $expiry = now()->subMinutes((int) config('powernest.power.payment_expiry_minutes'));

        PowerPurchase::where('status', Status::PendingPayment->value)
            ->where('created_at', '>=', $expiry)
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<=', now()->subMinutes(5)))
            ->limit(100)->get()
            ->each(function (PowerPurchase $p) use (&$stats) {
                $this->verifyWithGateway($p, 'reconciliation');
                $stats['verified']++;
            });

        PowerPurchase::where('status', Status::PendingPayment->value)->where('created_at', '<', $expiry)->limit(500)->get()
            ->each(function (PowerPurchase $p) use (&$stats) {
                DB::transaction(function () use ($p, &$stats) {
                    $locked = PowerPurchase::whereKey($p->id)->lockForUpdate()->first();
                    if ($locked?->status === Status::PendingPayment) {
                        $this->transition($locked, Status::Expired, null, 'Payment window elapsed');
                        $stats['expired']++;
                    }
                });
            });

        PowerPurchase::where('status', Status::Paid->value)->where('updated_at', '<=', now()->subMinutes(2))->limit(100)->pluck('id')
            ->each(function (int $id) use (&$stats) {
                VendElectricity::dispatch($id);
                $stats['resumed']++;
            });

        PowerPurchase::where('status', Status::Vending->value)->where('last_vend_attempt_at', '<=', now()->subMinutes(10))->limit(100)->get()
            ->each(function (PowerPurchase $p) use (&$stats) {
                DB::transaction(function () use ($p) {
                    $locked = PowerPurchase::whereKey($p->id)->lockForUpdate()->first();
                    if ($locked?->status === Status::Vending) {
                        $locked->failure_code = 'vend_interrupted';
                        $locked->failure_reason = 'Vending was interrupted. Check the Stron portal before retrying.';
                        $this->transition($locked, Status::ReconciliationRequired, null, 'Vend interrupted');
                    }
                });
                $this->notifyStaff($p->refresh());
                $stats['flagged']++;
            });

        return $stats;
    }

    private function transition(PowerPurchase $purchase, Status $to, ?User $actor, string $note): void
    {
        $from = $purchase->status;

        if (! $from->canTransitionTo($to)) {
            throw new LogicException("Illegal power purchase transition {$from->value} → {$to->value} for {$purchase->reference}.");
        }

        $purchase->status = $to;
        $purchase->save();
        $this->logEvent($purchase, $from, $to, $actor, $note);
    }

    private function logEvent(PowerPurchase $purchase, ?Status $from, Status $to, ?User $actor, string $note): void
    {
        PowerPurchaseEvent::create([
            'power_purchase_id' => $purchase->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'note' => mb_substr($note, 0, 250),
        ]);
    }

    private function authorizeReconcile(PowerPurchase $purchase, User $actor): void
    {
        abort_unless($actor->hasEstatePermission($purchase->estate, Permission::PowerReconcile), 403);
    }

    private function notifyStaff(PowerPurchase $purchase): void
    {
        $staff = app(MembershipService::class)->usersWithPermission($purchase->estate, Permission::PowerReconcile);
        Notification::send($staff, new PowerPurchaseNeedsAttention($purchase));
    }

    private function uniqueReference(): string
    {
        do {
            $reference = Reference::make('PNE', 12);
        } while (PowerPurchase::where('reference', $reference)->exists());

        return $reference;
    }
}
