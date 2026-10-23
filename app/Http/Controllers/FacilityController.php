<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Facility;
use App\Services\Operations\BookingService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function __construct(private EstateContext $context) {}

    public function index(Request $request): View
    {
        $estate = $this->context->estate();

        return view('facilities.index', [
            'facilities' => Facility::forEstate($estate)->where('is_active', true)->orderBy('name')->get(),
            'upcoming' => Booking::forEstate($estate)->where('user_id', $request->user()->id)->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Approved->value])->where('ends_at', '>=', now())->with('facility')->orderBy('starts_at')->get(),
        ]);
    }

    public function show(Facility $facility, Request $request): View
    {
        abort_unless($facility->is_active, 404);
        $tz = $this->context->estate()->timezone();
        $day = Carbon::parse($request->query('date', now($tz)->toDateString()), $tz)->startOfDay();

        $bookings = Booking::where('facility_id', $facility->id)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Approved->value])
            ->where('starts_at', '<', $day->copy()->endOfDay()->utc())->where('ends_at', '>', $day->copy()->utc())
            ->orderBy('starts_at')->get();

        return view('facilities.show', compact('facility', 'day', 'bookings', 'tz'));
    }

    public function book(Facility $facility, Request $request, BookingService $service): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'guests' => ['required', 'integer', 'min:1', 'max:500'],
            'purpose' => ['nullable', 'string', 'max:160'],
        ]);

        $tz = $this->context->estate()->timezone();
        $booking = $service->book($request->user(), $facility,
            Carbon::parse("{$data['date']} {$data['start_time']}", $tz)->utc(),
            Carbon::parse("{$data['date']} {$data['end_time']}", $tz)->utc(),
            (int) $data['guests'], $data['purpose'] ?? null);

        return redirect()->route('facilities.bookings.index')->with('success', $booking->status === BookingStatus::Approved ? 'Booking confirmed.' : 'Booking requested. You will be notified once it is approved.');
    }

    public function bookings(Request $request): View
    {
        return view('facilities.bookings', [
            'bookings' => Booking::forEstate($this->context->estate())->where('user_id', $request->user()->id)->with('facility')->latest('starts_at')->paginate(20),
        ]);
    }

    public function cancel(Booking $booking, Request $request, BookingService $service): RedirectResponse
    {
        Gate::authorize('cancel', $booking);
        $service->cancel($booking, $request->user());

        return back()->with('success', 'Booking cancelled.');
    }
}
