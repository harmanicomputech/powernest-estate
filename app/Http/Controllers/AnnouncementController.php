<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementCategory;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Building;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Services\Audit\AuditLogger;
use App\Support\EstateContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private EstateContext $context) {}

    public function index(Request $request): View
    {
        $announcements = $this->visibleTo($request->user())->with('author', 'building')
            ->orderByDesc('is_pinned')->latest('published_at')->paginate(15);

        $readIds = AnnouncementRead::where('user_id', $request->user()->id)->whereIn('announcement_id', $announcements->pluck('id'))->pluck('announcement_id');

        return view('announcements.index', compact('announcements', 'readIds'));
    }

    public function show(Announcement $announcement, Request $request): View
    {
        abort_unless($this->visibleTo($request->user())->whereKey($announcement->id)->exists() || Gate::allows('announcements.publish'), 404);
        AnnouncementRead::firstOrCreate(['announcement_id' => $announcement->id, 'user_id' => $request->user()->id], ['read_at' => now()]);

        return view('announcements.show', ['announcement' => $announcement->load('author', 'building')]);
    }

    public function create(): View
    {
        return view('announcements.create', ['buildings' => Building::forEstate($this->context->estate())->orderBy('name')->get()]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(AnnouncementCategory::class)],
            'audience' => ['required', Rule::in(['estate', 'building'])],
            'building_id' => ['nullable', 'required_if:audience,building', 'integer'],
            'is_pinned' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'notify' => ['nullable', 'boolean'],
        ]);

        $buildingId = $data['audience'] === 'building' ? Building::forEstate($estate)->findOrFail($data['building_id'])->id : null;

        $announcement = Announcement::create([
            'estate_id' => $estate->id,
            'author_id' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'category' => $data['category'],
            'audience' => $data['audience'],
            'building_id' => $buildingId,
            'is_pinned' => $request->boolean('is_pinned'),
            'published_at' => now(),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $audit->record('announcement.published', $announcement, ['audience' => $data['audience']], $estate);

        if ($request->boolean('notify') || $announcement->category === AnnouncementCategory::Emergency) {
            $recipients = User::where('status', 'active')
                ->whereHas('memberships', fn ($q) => $q->where('estate_id', $estate->id)->where('status', 'active'))
                ->when($buildingId, fn ($q) => $q->whereHas('occupancies', fn ($o) => $o->where('status', 'active')->whereHas('property', fn ($p) => $p->where('building_id', $buildingId))))
                ->get();
            Notification::send($recipients, new AnnouncementPublished($announcement));
        }

        return redirect()->route('announcements.show', $announcement)->with('success', 'Announcement published.');
    }

    private function visibleTo(User $user): Builder
    {
        $estate = $this->context->estate();
        $buildingIds = $user->authorizedProperties($estate)->whereNotNull('building_id')->pluck('building_id');

        return Announcement::forEstate($estate)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->where('audience', 'estate')
                ->orWhere(fn ($b) => $b->where('audience', 'building')->whereIn('building_id', $buildingIds))
                ->when(Gate::allows('announcements.publish'), fn ($w) => $w->orWhereNotNull('id')));
    }
}
