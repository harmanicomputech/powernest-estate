<x-layouts.app :title="$inspection->title">
    <x-ui.page-header :title="$inspection->title" :eyebrow="($inspection->location ?? 'Inspection').' · '.($inspection->inspector?->name ?? '')" :back="route('inspections.index')">
        @if ($inspection->isComplete())
            @if ($inspection->failed_count)<x-ui.badge tone="danger" dot>{{ $inspection->failed_count }} failed</x-ui.badge>@else<x-ui.badge tone="success" dot>All clear</x-ui.badge>@endif
        @else
            <x-ui.badge tone="info" dot>In progress</x-ui.badge>
        @endif
    </x-ui.page-header>

    @if ($inspection->isComplete())
        <x-ui.card :padded="false" class="mx-auto max-w-2xl">
            @foreach ($inspection->results as $row)
                <div class="flex items-start gap-3 border-t border-line px-5 py-3.5 first:border-t-0 sm:px-6">
                    <x-icon :name="['pass' => 'circle-check', 'fail' => 'circle-x', 'na' => 'circle-dashed'][$row['result']]" @class(['mt-0.5 size-5 shrink-0', 'text-success' => $row['result'] === 'pass', 'text-danger' => $row['result'] === 'fail', 'text-faint' => $row['result'] === 'na']) />
                    <div class="min-w-0 flex-1"><p class="text-sm font-semibold">{{ $row['item'] }}</p>@if ($row['note'])<p class="text-sm text-muted">{{ $row['note'] }}</p>@endif</div>
                    <span class="text-xs font-bold uppercase text-muted">{{ $row['result'] === 'na' ? 'N/A' : $row['result'] }}</span>
                </div>
            @endforeach
            @if ($inspection->notes)<div class="border-t border-line bg-canvas px-5 py-4 text-sm sm:px-6">{{ $inspection->notes }}</div>@endif
            <p class="border-t border-line px-5 py-3 text-xs text-muted sm:px-6">Completed {{ local_time($inspection->completed_at) }}</p>
        </x-ui.card>
    @else
        <form method="POST" action="{{ route('inspections.complete', $inspection) }}" class="mx-auto max-w-2xl space-y-3" x-data="submitting" x-on:submit="submit">
            @csrf
            @foreach ($inspection->results as $i => $row)
                <div class="card p-4" x-data="{ result: @js(old("answers.$i.result")) }">
                    <p class="font-semibold">{{ $row['item'] }}</p>
                    <div class="mt-3 grid grid-cols-3 gap-2" role="radiogroup" aria-label="{{ $row['item'] }}">
                        @foreach (['pass' => 'Pass', 'fail' => 'Fail', 'na' => 'N/A'] as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="answers[{{ $i }}][result]" value="{{ $value }}" x-model="result" class="peer sr-only">
                                <span @class([
                                    'flex h-11 items-center justify-center rounded-xl border border-line-strong text-sm font-bold transition',
                                    'peer-checked:border-success peer-checked:bg-success-soft peer-checked:text-success' => $value === 'pass',
                                    'peer-checked:border-danger peer-checked:bg-danger-soft peer-checked:text-danger' => $value === 'fail',
                                    'peer-checked:border-ink-soft peer-checked:bg-sunken' => $value === 'na',
                                ])>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <input x-show="result === 'fail'" x-cloak name="answers[{{ $i }}][note]" value="{{ old("answers.$i.note") }}" class="control mt-2" placeholder="What's wrong?">
                    @error("answers.$i.result")<p class="mt-1.5 text-[0.8125rem] font-medium text-danger">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <x-ui.card>
                <x-ui.textarea name="notes" label="General notes" rows="3" optional />
                <div class="mt-4"><x-ui.checkbox name="raise_maintenance" label="Raise a maintenance request for failed items" checked /></div>
                <x-ui.button class="mt-5 w-full" size="lg" busy icon="check">Complete inspection</x-ui.button>
            </x-ui.card>
        </form>
    @endif
</x-layouts.app>
