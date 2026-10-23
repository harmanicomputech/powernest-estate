<?php

namespace App\Notifications;

use App\Models\StaffTask;

class TaskAssigned extends AppNotification
{
    protected string $category = 'operations';

    protected bool $essential = false;

    public function __construct(public StaffTask $task) {}

    protected function title(): string
    {
        return "New task: {$this->task->title}";
    }

    protected function body(): string
    {
        return $this->task->due_at ? "Due ".$this->task->due_at->diffForHumans()."." : "No due date.";
    }

    protected function url(): ?string
    {
        return route('tasks.index');
    }

    protected function icon(): string
    {
        return 'clipboard-list';
    }

    protected function estateId(): ?int
    {
        return $this->task->estate_id;
    }
}
