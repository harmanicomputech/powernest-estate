@php
    use App\Enums\PowerPurchaseStatus as S;
    $s = $purchase->status;
    $steps = [
        ['Payment initiated', true],
        ['Payment confirmed', $s->isPaymentConfirmed()],
        ['Vending', in_array($s, [S::Vending, S::TokenIssued], true) || $purchase->vend_attempts > 0],
        ['Token generated', $s === S::TokenIssued],
    ];
@endphp
<div @if($polling) wire:poll.3s @endif>
    @if ($s === S::TokenIssued && $purchase->token)
        <div class="relative overflow-hidden rounded-[1.5rem] bg-brand-900 p-6 text-white sm:p-8 animate-pop">
            <div class="pointer-events-none absolute -right-20 -top-20 size-64 rounded-full bg-gold-400/20 blur-3xl"></div>
            <div class="relative">
                <div class="flex items-center gap-2 text-sm font-semibold text-gold-200"><x-icon name="circle-check" class="size-5" />Token generated</div>
                <p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-brand-200/80">Your electricity token</p>
                <p class="mt-2 break-all font-mono text-[1.65rem] font-bold leading-snug tracking-[0.08em] tnum sm:text-[2rem]">{{ trim(chunk_split($purchase->token->token, 4, ' ')) }}</p>
                <div class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-brand-100/85">
                    @if ($purchase->token->units)<span><span class="font-bold text-white tnum">{{ $purchase->token->units }}</span> {{ $purchase->token->unit_label }}</span>@endif
                    <span>Meter {{ $purchase->meter_number }}</span>
                    <span>{{ local_time($purchase->token->issued_at, 'j M Y, g:ia') }}</span>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-2 sm:flex">
                    <div x-data="clipboard(@js($purchase->token->token))">
                        <x-ui.button type="button" variant="gold" class="w-full" icon="copy" x-on:click="copy()"><span x-text="copied ? 'Copied!' : 'Copy token'">Copy token</span></x-ui.button>
                    </div>
                    <div x-data="share(@js(['title' => 'Electricity token', 'text' => 'Token for meter '.$purchase->meter_number.': '.trim(chunk_split($purchase->token->token, 4, ' '))]))">
                        <x-ui.button type="button" variant="inverse" class="w-full" icon="share-2" x-on:click="share()"><span x-text="shared ? 'Copied' : 'Share'">Share</span></x-ui.button>
                    </div>
                </div>
                @if ($purchase->token->source === 'demo_seed')
                    <p class="mt-4 text-xs text-brand-200/70">Demo data — this is not a real token.</p>
                @endif
            </div>
        </div>
    @else
        <div class="card card-pad">
            @php
                [$tone, $icon, $title, $body] = match ($s) {
                    S::PendingPayment => ['neutral', 'credit-card', 'Waiting for payment', 'We have not received confirmation of your payment yet. If you paid by bank transfer it can take a few minutes.'],
                    S::Paid, S::Vending => ['info', 'loader', 'Generating your token…', 'Payment confirmed. We are requesting your token from the vending service. This usually takes a few seconds.'],
                    S::PaymentFailed => ['danger', 'circle-x', 'Payment not completed', $purchase->failure_reason ?: 'The payment provider reported that this payment did not go through. You have not been charged for electricity.'],
                    S::VendFailed => ['danger', 'triangle-alert', 'Token could not be generated', 'Your payment is safe and confirmed. The estate team has been alerted and will issue your token or refund you.'],
                    S::ReconciliationRequired => ['warning', 'triangle-alert', 'We are checking this purchase', 'Your payment is recorded. The vending result needs to be confirmed by the estate team before a token can be shown. You will be notified.'],
                    S::Expired => ['neutral', 'hourglass', 'Payment window expired', 'No payment was received for this purchase. Start a new purchase if you still need electricity.'],
                    S::Refunded => ['neutral', 'refresh-cw', 'Refunded', 'This purchase was refunded by the estate team.'],
                    default => ['neutral', 'info', $s->label(), ''],
                };
                $toneClasses = ['neutral' => 'bg-sunken text-ink-soft', 'info' => 'bg-info-soft text-info', 'danger' => 'bg-danger-soft text-danger', 'warning' => 'bg-warning-soft text-warning'];
            @endphp
            <div class="flex items-start gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl {{ $toneClasses[$tone] }}"><x-icon :name="$icon" @class(['size-6', 'animate-spin' => $icon === 'loader']) /></span>
                <div>
                    <p class="text-lg font-extrabold">{{ $title }}</p>
                    <p class="mt-1 text-sm text-muted">{{ $body }}</p>
                </div>
            </div>
        </div>
    @endif

    <ol class="mt-6 grid grid-cols-4 gap-2" aria-label="Purchase progress">
        @foreach ($steps as $i => [$label, $done])
            <li class="flex flex-col gap-2">
                <span @class(['h-1.5 rounded-full transition-colors duration-500', 'bg-brand-500' => $done, 'bg-line' => ! $done])></span>
                <span @class(['text-[0.6875rem] font-semibold leading-tight sm:text-xs', 'text-ink' => $done, 'text-faint' => ! $done])>{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</div>
