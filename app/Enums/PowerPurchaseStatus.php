<?php

namespace App\Enums;

enum PowerPurchaseStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Vending = 'vending';
    case TokenIssued = 'token_issued';
    case PaymentFailed = 'payment_failed';
    case VendFailed = 'vend_failed';
    case ReconciliationRequired = 'reconciliation_required';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Payment initiated',
            self::Paid => 'Payment confirmed',
            self::Vending => 'Vending pending',
            self::TokenIssued => 'Token generated',
            self::PaymentFailed => 'Payment failed',
            self::VendFailed => 'Vending failed',
            self::ReconciliationRequired => 'Reconciliation required',
            self::Expired => 'Expired',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * Badge tone used by the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::PendingPayment => 'neutral',
            self::Paid => 'info',
            self::Vending => 'info',
            self::TokenIssued => 'success',
            self::PaymentFailed => 'danger',
            self::VendFailed => 'danger',
            self::ReconciliationRequired => 'warning',
            self::Expired => 'neutral',
            self::Refunded => 'neutral',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }

    /**
     * Allowed state transitions. Anything not listed here is rejected.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::PaymentFailed, self::Expired, self::ReconciliationRequired],
            self::Expired, self::PaymentFailed => [self::Paid, self::ReconciliationRequired],
            self::Paid => [self::Vending],
            self::Vending => [self::TokenIssued, self::VendFailed, self::ReconciliationRequired, self::Paid],
            self::VendFailed => [self::Vending, self::Refunded, self::TokenIssued],
            self::ReconciliationRequired => [self::Vending, self::TokenIssued, self::Refunded],
            self::TokenIssued, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::TokenIssued, self::Refunded], true);
    }

    /**
     * Whether the customer's money has been confirmed as received.
     */
    public function isPaymentConfirmed(): bool
    {
        return in_array($this, [self::Paid, self::Vending, self::TokenIssued, self::VendFailed, self::ReconciliationRequired, self::Refunded], true);
    }

    public function needsAttention(): bool
    {
        return in_array($this, [self::VendFailed, self::ReconciliationRequired], true);
    }
}
