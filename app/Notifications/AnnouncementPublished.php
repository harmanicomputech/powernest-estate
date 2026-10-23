<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Support\Str;

class AnnouncementPublished extends AppNotification
{
    protected string $category = 'announcements';

    protected bool $essential = false;

    public function __construct(public Announcement $announcement) {}

    protected function title(): string
    {
        return $this->announcement->title;
    }

    protected function body(): string
    {
        return Str::limit(strip_tags($this->announcement->body), 140);
    }

    protected function url(): ?string
    {
        return route('announcements.show', $this->announcement);
    }

    protected function icon(): string
    {
        return 'megaphone';
    }

    protected function estateId(): ?int
    {
        return $this->announcement->estate_id;
    }
}
