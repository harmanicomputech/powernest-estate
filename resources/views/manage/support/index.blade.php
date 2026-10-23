<x-layouts.app title="Support">
    <x-ui.page-header title="Support requests" description="Resident questions about electricity purchases and payments." />
    <x-ui.tabs :active="$status" :tabs="['open' => ['Open', route('manage.support.index')], 'resolved' => ['Resolved', route('manage.support.index', ['status' => 'resolved'])]]" />
    <div class="space-y-4">
        @forelse ($tickets as $ticket)
            <x-ui.card :padded="false">
                <div class="flex flex-wrap items-center gap-3 p-5 sm:p-6">
                    <div class="min-w-0 flex-1"><p class="font-semibold">{{ $ticket->subject }}</p><p class="text-sm text-muted">{{ $ticket->reference }} · {{ $ticket->user->name }} · {{ $ticket->created_at->diffForHumans() }}</p></div>
                    @if ($ticket->purchase)<x-ui.button :href="route('manage.power.show', $ticket->purchase)" size="sm" variant="secondary">Open purchase</x-ui.button>@endif
                </div>
                @foreach ($ticket->messages as $message)
                    <div @class(['border-t border-line px-5 py-3 text-sm sm:px-6', 'bg-brand-50/40' => $message->is_staff])><span class="font-semibold">{{ $message->user?->name }}</span> <span class="text-faint">· {{ $message->created_at->diffForHumans() }}</span><p class="mt-0.5">{{ $message->body }}</p></div>
                @endforeach
                @if ($ticket->status === 'open')
                    <form method="POST" action="{{ route('manage.support.reply', $ticket) }}" class="flex flex-wrap items-center gap-2 border-t border-line p-5 sm:p-6">@csrf
                        <input name="body" class="control min-w-[12rem] flex-1" placeholder="Reply / internal note" required>
                        <label class="inline-flex items-center gap-1.5 text-sm text-muted"><input type="checkbox" name="resolve" value="1" class="accent-brand-700">Resolve</label>
                        <x-ui.button size="sm">Save</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        @empty
            <x-ui.card><x-ui.empty icon="life-buoy" title="No support requests" /></x-ui.card>
        @endforelse
    </div>
    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layouts.app>
