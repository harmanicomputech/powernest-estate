<x-layouts.app :title="$role->label()">
    <x-ui.page-header :title="$role->label()" :back="route('manage.roles.index')" :description="$locked ? 'Estate Owners always have every permission so the estate can never be locked out.' : 'Tick what people with this role can do. Changes apply immediately to everyone with the role.'">
        @if ($customised && ! $locked)
            <x-ui.confirm :action="route('manage.roles.reset', $role)" method="DELETE" title="Restore standard permissions?" message="This role goes back to the permissions it came with." confirm="Restore" variant="ghost" icon="rotate-ccw">Restore standard</x-ui.confirm>
        @endif
    </x-ui.page-header>

    <form method="POST" action="{{ route('manage.roles.update', $role) }}" x-data="{ count: {{ count($granted) }} }" x-on:change="count = $el.querySelectorAll('input[name=\'permissions[]\']:checked').length">
        @csrf @method('PUT')
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($groups as $group => $permissions)
                <x-ui.card :title="$group">
                    <div class="space-y-1">
                        @foreach ($permissions as $permission)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl px-2 py-2.5 hover:bg-canvas" data-press="soft">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->value }}" class="size-[1.125rem] shrink-0 accent-brand-700" @checked(in_array($permission->value, $granted, true)) @disabled($locked)>
                                <span class="flex-1 text-sm font-medium">{{ $permission->label() }}</span>
                                @if (! $locked && in_array($permission->value, $defaults, true) !== in_array($permission->value, $granted, true))
                                    <x-ui.badge tone="info">Changed</x-ui.badge>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach
        </div>
        @unless ($locked)
            <div class="sticky bottom-24 z-10 mt-6 flex items-center justify-between gap-3 rounded-2xl border border-line bg-surface/95 p-3 pl-5 shadow-[var(--shadow-raised)] backdrop-blur lg:bottom-6">
                <p class="text-sm text-muted"><span class="font-bold text-ink tnum" x-text="count">{{ count($granted) }}</span> permissions selected</p>
                <x-ui.button icon="check">Save permissions</x-ui.button>
            </div>
        @endunless
    </form>
</x-layouts.app>
