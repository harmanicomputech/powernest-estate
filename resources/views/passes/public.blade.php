@php $status = $pass->effectiveStatus(); $active = $status === \App\Enums\PassStatus::Active; @endphp
<!DOCTYPE html>
<html lang="en">
<head><x-layouts.head title="Visitor pass" /><meta name="robots" content="noindex, nofollow"></head>
<body class="min-h-dvh bg-brand-950 px-4 pb-10 pt-safe">
    <div class="mx-auto max-w-sm pt-8">
        <div class="mb-6 flex justify-center"><x-brand.logo inverse /></div>
        <div class="overflow-hidden rounded-[1.75rem] bg-surface shadow-2xl animate-pop">
            <div class="bg-brand-800 px-6 py-5 text-center text-white">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-200/80">Visitor pass</p>
                <p class="mt-1 text-xl font-extrabold">{{ $pass->visitor_name }}</p>
                <p class="mt-1 text-sm text-brand-100/80">{{ $pass->estate->name }} · {{ $pass->property->identifier }}</p>
            </div>
            <div class="p-6 text-center">
                @if ($active)
                    <div class="mx-auto w-full max-w-[16rem] [&_svg]:h-auto [&_svg]:w-full">{!! \App\Http\Controllers\VisitorController::qrSvg(\App\Services\Visitors\PassService::qrPayload($pass)) !!}</div>
                    <p class="eyebrow mt-5">Or give this code at the gate</p>
                    <p class="mt-1 font-mono text-[2.25rem] font-extrabold tracking-[0.2em] tnum">{{ $pass->formattedCode() }}</p>
                @else
                    <div class="py-10"><x-icon name="circle-x" class="mx-auto size-12 text-faint" /><p class="mt-3 font-bold">This pass is {{ strtolower($status->label()) }}</p><p class="text-sm text-muted">Ask your host for a new pass.</p></div>
                @endif
                <div class="mt-5 rounded-xl bg-canvas px-4 py-3 text-sm">
                    <p class="font-semibold">{{ $pass->valid_from->timezone($pass->estate->timezone())->format('D j M, g:ia') }}</p>
                    <p class="text-muted">until {{ $pass->valid_until->timezone($pass->estate->timezone())->format('D j M, g:ia') }}</p>
                </div>
                <p class="mt-4 text-xs text-muted">Host: {{ $pass->host->firstName() }}. Security will verify this pass when you arrive. Do not share it with anyone else.</p>
            </div>
        </div>
        @if ($pass->estate->address)<p class="mt-6 text-center text-sm text-brand-200/70"><x-icon name="map-pin" class="mr-1 inline size-4" />{{ $pass->estate->address }}</p>@endif
    </div>
</body>
</html>
