<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\StaffTask;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\Estate\MembershipService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private EstateContext $context) {}

    public function index(Request $request): View
    {
        $estate = $this->context->estate();
        $user = $request->user();
        $isManager = $user->hasEstatePermission($estate, Permission::StaffManage);
        $tab = $request->query('tab', 'open');

        $tasks = StaffTask::forEstate($estate)->with('assignee', 'creator')
            ->when(! $isManager || $tab === 'mine', fn ($q) => $q->where('assigned_to', $user->id))
            ->when($tab !== 'done', fn ($q) => $q->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value]))
            ->when($tab === 'done', fn ($q) => $q->whereIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value]))
            ->orderByRaw('due_at IS NULL')->orderBy('due_at')->paginate(25)->withQueryString();

        $staff = $isManager ? app(MembershipService::class)->usersWithPermission($estate, Permission::TasksWork)->sortBy('name')->values() : collect();

        return view('tasks.index', compact('tasks', 'tab', 'isManager', 'staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['required', 'integer'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'due_at' => ['nullable', 'date'],
        ]);

        $assignee = User::findOrFail($data['assigned_to']);
        abort_unless($assignee->hasEstatePermission($estate, Permission::TasksWork), 422, 'This person cannot receive tasks.');

        $task = StaffTask::create([
            'estate_id' => $estate->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assigned_to' => $assignee->id,
            'created_by' => $request->user()->id,
            'priority' => $data['priority'],
            'status' => TaskStatus::Open,
            'due_at' => ! empty($data['due_at']) ? Carbon::parse($data['due_at'], $estate->timezone())->utc() : null,
        ]);

        if ($assignee->isNot($request->user())) {
            $assignee->notify(new TaskAssigned($task));
        }

        return back()->with('success', 'Task created.');
    }

    public function status(StaffTask $task, Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($task->assigned_to === $user->id || $user->hasEstatePermission($task->estate, Permission::StaffManage), 403);
        $data = $request->validate(['status' => ['required', Rule::enum(TaskStatus::class)]]);
        $status = TaskStatus::from($data['status']);

        $task->update(['status' => $status, 'completed_at' => $status === TaskStatus::Done ? now() : null]);

        return back()->with('success', "Task marked {$status->label()}.");
    }
}
