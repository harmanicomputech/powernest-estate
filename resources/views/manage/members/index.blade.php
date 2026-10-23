<x-layouts.app title="Members & access">
    <x-ui.page-header title="Members & access" description="Estate staff accounts, pending invitations and resident access requests.">
        <x-ui.button type="button" icon="user-plus" x-data x-on:click="$dispatch('open-modal', 'invite-staff')">Invite staff</x-ui.button>
    </x-ui.page-header>
    <x-ui.tabs :active="$tab" :tabs="['staff' => ['Staff', route('manage.members.index'), $staff->count()], 'invitations' => ['Invitations', route('manage.members.index', ['tab' => 'invitations']), $invitations->count()], 'requests' => ['Access requests', route('manage.members.index', ['tab' => 'requests']), $requests->count()]]" />

    <x-ui.card :padded="false">
        @if ($tab === 'staff')
            @forelse ($staff as $membership)
                <div class="flex flex-wrap items-center gap-3 px-5 py-3.5 sm:px-6 @if(! $loop->first) border-t border-line @endif">
                    <x-ui.avatar :user="$membership->user" />
                    <div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ $membership->user->name }}</p><p class="truncate text-sm text-muted">{{ $membership->role->label() }}{{ $membership->title ? ' · '.$membership->title : '' }} · {{ $membership->user->email }}</p></div>
                    <x-ui.status :value="$membership->status" />
                    @if ($membership->user_id !== auth()->id())
                        <form method="POST" action="{{ route('manage.members.status', $membership) }}">@csrf
                            @if ($membership->status === \App\Enums\MembershipStatus::Active)
                                <x-ui.button name="status" value="suspended" size="sm" variant="ghost">Suspend</x-ui.button>
                            @else
                                <x-ui.button name="status" value="active" size="sm" variant="secondary">Reinstate</x-ui.button>
                            @endif
                        </form>
                    @endif
                </div>
            @empty
                <x-ui.empty icon="users" title="No staff yet" />
            @endforelse
        @elseif ($tab === 'invitations')
            @forelse ($invitations as $invitation)
                <div class="flex flex-wrap items-center gap-3 px-5 py-3.5 sm:px-6 @if(! $loop->first) border-t border-line @endif">
                    <span class="grid size-10 place-items-center rounded-full bg-sunken text-muted"><x-icon name="mail" class="size-4" /></span>
                    <div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ $invitation->name ?? $invitation->email }}</p><p class="truncate text-sm text-muted">{{ $invitation->role->label() }}{{ $invitation->property ? ' · '.$invitation->property->identifier : '' }} · {{ $invitation->email }} · {{ $invitation->expires_at->isPast() ? 'expired' : 'expires '.$invitation->expires_at->diffForHumans() }}</p></div>
                    <form method="POST" action="{{ route('manage.invitations.revoke', $invitation) }}">@csrf<x-ui.button size="sm" variant="ghost">Withdraw</x-ui.button></form>
                </div>
            @empty
                <x-ui.empty icon="mail" title="No pending invitations" />
            @endforelse
        @else
            @forelse ($requests as $req)
                <div class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6 @if(! $loop->first) border-t border-line @endif">
                    <x-ui.avatar :user="$req->user" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $req->user->name }} <span class="font-normal text-muted">· {{ $req->user->email }}</span></p>
                        <p class="text-sm text-muted">{{ $req->relationship->label() }} of {{ $req->property->identifier }} · {{ $req->created_at->diffForHumans() }}</p>
                        @if ($req->message)<p class="mt-1 text-sm text-ink-soft">“{{ $req->message }}”</p>@endif
                    </div>
                    <form method="POST" action="{{ route('manage.requests.review', $req) }}" class="flex gap-1.5">@csrf
                        <x-ui.button name="decision" value="reject" size="sm" variant="ghost">Decline</x-ui.button>
                        <x-ui.button name="decision" value="approve" size="sm" icon="check">Approve</x-ui.button>
                    </form>
                </div>
            @empty
                <x-ui.empty icon="key-round" title="No pending requests" description="Residents who request access with the estate code appear here." />
            @endforelse
        @endif
    </x-ui.card>

    <x-ui.modal name="invite-staff" title="Invite staff member">
        <form method="POST" action="{{ route('manage.members.invite') }}" class="space-y-4">@csrf
            <x-ui.input name="name" label="Name" optional />
            <x-ui.input name="email" type="email" label="Email" required />
            <x-ui.select name="role" label="Role" :options="$staffRoles" />
            <x-ui.button class="w-full" icon="send">Send invitation</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
