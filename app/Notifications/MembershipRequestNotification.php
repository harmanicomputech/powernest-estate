<?php

namespace App\Notifications;

use App\Models\MembershipRequest;

class MembershipRequestNotification extends AppNotification
{
    protected string $category = 'operations';

    public function __construct(public MembershipRequest $request) {}

    protected function title(): string
    {
        return 'New resident access request';
    }

    protected function body(): string
    {
        return "{$this->request->user->name} asked to join as {$this->request->relationship->label()} of {$this->request->property->identifier}.";
    }

    protected function url(): ?string
    {
        return route('manage.members.index', ['tab' => 'requests']);
    }

    protected function icon(): string
    {
        return 'user-plus';
    }

    protected function estateId(): ?int
    {
        return $this->request->estate_id;
    }
}
