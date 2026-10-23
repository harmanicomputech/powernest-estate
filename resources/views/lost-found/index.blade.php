<x-layouts.app title="Lost & found">
    <x-ui.page-header title="Lost & found" description="Lost something in the estate, or found something that isn't yours? Post it here.">
        <x-ui.button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'lost-found')">Post an item</x-ui.button>
    </x-ui.page-header>
    <x-ui.tabs :active="$status" :tabs="['open' => ['Open', route('lost-found.index')], 'resolved' => ['Returned', route('lost-found.index', ['status' => 'resolved'])]]" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($items as $item)
            <div class="card flex flex-col p-5">
                <div class="flex items-center gap-2">
                    <x-ui.badge :tone="$item->type === 'found' ? 'success' : 'warning'" dot>{{ $item->type === 'found' ? 'Found' : 'Lost' }}</x-ui.badge>
                    <span class="ml-auto text-xs text-faint">{{ $item->created_at->diffForHumans() }}</span>
                </div>
                <p class="mt-3 font-bold">{{ $item->title }}</p>
                @if ($item->description)<p class="mt-1 flex-1 text-sm text-muted">{{ $item->description }}</p>@endif
                @if ($item->location)<p class="mt-2 flex items-center gap-1.5 text-sm text-ink-soft"><x-icon name="map-pin" class="size-4 text-muted" />{{ $item->location }}</p>@endif
                <div class="mt-4 flex items-center justify-between border-t border-line pt-3">
                    <span class="text-xs text-muted">
                        @can('incidents.manage') Posted by {{ $item->reporter?->name }} @else {{ $item->reported_by === auth()->id() ? 'Posted by you' : 'Contact the security office' }} @endcan
                    </span>
                    @if ($item->status === 'open' && ($item->reported_by === auth()->id() || auth()->user()->can('incidents.manage')))
                        <form method="POST" action="{{ route('lost-found.resolve', $item) }}">@csrf<x-ui.button size="sm" variant="secondary" icon="check">Returned</x-ui.button></form>
                    @endif
                </div>
            </div>
        @empty
            <x-ui.card class="sm:col-span-2 lg:col-span-3"><x-ui.empty icon="search" title="Nothing listed" description="Lost and found items posted by residents and security appear here." /></x-ui.card>
        @endforelse
    </div>
    <div class="mt-4">{{ $items->links() }}</div>

    <x-ui.modal name="lost-found" title="Post an item">
        <form method="POST" action="{{ route('lost-found.store') }}" class="space-y-4">@csrf
            <div class="grid grid-cols-2 gap-2" role="radiogroup">
                @foreach (['lost' => 'I lost something', 'found' => 'I found something'] as $value => $label)
                    <label class="cursor-pointer"><input type="radio" name="type" value="{{ $value }}" class="peer sr-only" @checked($loop->first)><span class="flex h-11 items-center justify-center rounded-xl border border-line-strong text-sm font-semibold peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-800">{{ $label }}</span></label>
                @endforeach
            </div>
            <x-ui.input name="title" label="What is it?" required placeholder="e.g. Black wallet, house keys" />
            <x-ui.textarea name="description" label="Details" rows="3" optional />
            <x-ui.input name="location" label="Where?" optional />
            <x-ui.button class="w-full">Post</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
