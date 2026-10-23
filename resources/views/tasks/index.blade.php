<x-layouts.app title="Tasks">
    <x-ui.page-header title="Tasks" :description="$isManager ? 'Assign and track staff work.' : 'Work assigned to you.'">
        @if ($isManager)<x-ui.button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'new-task')">New task</x-ui.button>@endif
    </x-ui.page-header>
    <x-ui.tabs :active="$tab" :tabs="array_filter(['open' => [$isManager ? 'All open' : 'Open', route('tasks.index')], 'mine' => $isManager ? ['Mine', route('tasks.index', ['tab' => 'mine'])] : null, 'done' => ['Done', route('tasks.index', ['tab' => 'done'])]])" />
    <x-ui.card :padded="false">
        @forelse ($tasks as $task)
            <div class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $task->title }}</p>
                    <p class="text-sm text-muted">{{ $task->assignee?->name }} · {{ $task->due_at ? 'due '.local_time($task->due_at, 'D j M, g:ia') : 'no due date' }}@if($task->due_at?->isPast() && $task->status !== \App\Enums\TaskStatus::Done) · <span class="font-semibold text-danger">overdue</span>@endif</p>
                    @if ($task->description)<p class="mt-1 text-sm text-ink-soft">{{ $task->description }}</p>@endif
                </div>
                <x-ui.status :value="$task->priority" />
                <form method="POST" action="{{ route('tasks.status', $task) }}" class="flex gap-1.5">@csrf
                    @if ($task->status === \App\Enums\TaskStatus::Open)<x-ui.button name="status" value="in_progress" size="sm" variant="secondary">Start</x-ui.button>@endif
                    @if (in_array($task->status, [\App\Enums\TaskStatus::Open, \App\Enums\TaskStatus::InProgress]))<x-ui.button name="status" value="done" size="sm" icon="check">Done</x-ui.button>@else<x-ui.status :value="$task->status" />@endif
                </form>
            </div>
        @empty
            <x-ui.empty icon="clipboard-list" title="No tasks" />
        @endforelse
    </x-ui.card>
    <div class="mt-4">{{ $tasks->links() }}</div>

    @if ($isManager)
        <x-ui.modal name="new-task" title="New task">
            <form method="POST" action="{{ route('tasks.store') }}" class="space-y-4">@csrf
                <x-ui.input name="title" label="Task" required />
                <x-ui.textarea name="description" label="Details" rows="3" optional />
                <x-ui.select name="assigned_to" label="Assign to" :options="$staff->pluck('name', 'id')->all()" required placeholder="Choose" />
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.select name="priority" label="Priority" :options="\App\Enums\Priority::options()" value="normal" />
                    <x-ui.input name="due_at" type="datetime-local" label="Due" optional />
                </div>
                <x-ui.button class="w-full">Create task</x-ui.button>
            </form>
        </x-ui.modal>
    @endif
</x-layouts.app>
