<?php

namespace App\Http\Controllers\Manage;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Facility;
use App\Services\Operations\BookingService;
use App\Support\EstateContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(private EstateContext $context) {}

    public function index(Request $request): View
    {
        $estate = $this->context->estate();

        return view('manage.bookings.index', [
            'pending' => Booking::forEstate($estate)->where('status', BookingStatus::Pending->value)->with('facility', 'user', 'property')->orderBy('starts_at')->get(),
            'upcoming' => Booking::forEstate($estate)->where('status', BookingStatus::Approved->value)->where('ends_at', '>=', now())->with('facility', 'user', 'property')->orderBy('starts_at')->limit(30)->get(),
            'facilities' => Facility::forEstate($estate)->withCount(['bookings' => fn ($q) => $q->where('status', 'approved')])->orderBy('name')->get(),
        ]);
    }

    public function review(Booking $booking, Request $request, BookingService $service): RedirectResponse
    {
        Gate::authorize('review', $booking);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:250']]);
        $service->review($booking, $request->user(), $data['decision'] === 'approve', $data['note'] ?? null);

        return back()->with('success', 'Booking '.($data['decision'] === 'approve' ? 'approved' : 'declined').'.');
    }

    public function storeFacility(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'fee' => ['nullable', 'string', 'max:15'],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i', 'after:opens_at'],
            'max_duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'requires_approval' => ['nullable', 'boolean'],
            'rules' => ['nullable', 'string', 'max:2000'],
        ]);

        Facility::create([
            'estate_id' => $this->context->estate()->id,
            'fee' => ! empty($data['fee']) ? Money::parse($data['fee']) : 0,
            'requires_approval' => $request->boolean('requires_approval'),
        ] + collect($data)->except('fee', 'requires_approval')->all());

        return back()->with('success', "{$data['name']} added.");
    }
}
