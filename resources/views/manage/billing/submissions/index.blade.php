<x-layouts.app title="Receipts to verify">
    <x-ui.page-header title="Receipts to verify" description="Offline payments uploaded by residents. Check each against the bank statement or cash book before approving." :back="route('manage.billing.index')" />
    <x-ui.tabs :active="$status->value" :tabs="collect(\App\Enums\SubmissionStatus::cases())->mapWithKeys(fn ($s) => [$s->value => [$s === \App\Enums\SubmissionStatus::Pending ? 'Awaiting review' : $s->label(), route('manage.billing.submissions.index', ['status' => $s->value]), $counts[$s->value] ?? 0]])->all()" />
    <x-ui.card :padded="false">
        @forelse ($submissions as $submission)
            <a href="{{ route('manage.billing.submissions.show', $submission) }}" class="flex flex-wrap items-center gap-4 px-5 py-4 transition hover:bg-canvas sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <x-ui.avatar :user="$submission->submitter" />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $submission->submitter->name }} <span class="font-normal text-muted">· {{ $submission->property->identifier }}</span></p>
                    <p class="truncate text-sm text-muted">{{ $submission->invoice->number }} · {{ \App\Models\PaymentSubmission::METHODS[$submission->method] ?? $submission->method }} on {{ $submission->paid_on->format('j M Y') }}{{ $submission->payer_reference ? ' · ref '.$submission->payer_reference : '' }}</p>
                    @if ($submission->reviewer)<p class="text-xs text-faint">{{ $submission->status->label() }} by {{ $submission->reviewer->name }} {{ $submission->reviewed_at->diffForHumans() }}</p>@endif
                </div>
                <div class="text-right">
                    <p class="font-bold tnum">{{ money($submission->amount) }}</p>
                    <p class="text-xs text-muted">{{ $submission->created_at->diffForHumans() }}</p>
                </div>
                <x-icon name="chevron-right" class="size-4 text-faint" />
            </a>
        @empty
            <x-ui.empty icon="file-check" :title="$status === \App\Enums\SubmissionStatus::Pending ? 'Nothing to verify' : 'No receipts here'" :description="$status === \App\Enums\SubmissionStatus::Pending ? 'Receipts uploaded by residents for offline payments will appear here.' : null" />
        @endforelse
    </x-ui.card>
    <div class="mt-4">{{ $submissions->links() }}</div>
</x-layouts.app>
