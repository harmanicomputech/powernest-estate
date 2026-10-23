<?php

namespace App\Http\Controllers;

use App\Enums\PassStatus;
use App\Enums\VisitStatus;
use App\Models\Gate as EstateGate;
use App\Models\Property;
use App\Models\SecurityShift;
use App\Models\Visit;
use App\Models\VisitorPass;
use App\Services\Visitors\GateService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GateController extends Controller
{
    public function __construct(private EstateContext $context) {}

    public function index(Request $request): View
    {
        $estate = $this->context->estate();

        return view('gate.index', [
            'shift' => SecurityShift::forEstate($estate)->where('user_id', $request->user()->id)->whereNull('ended_at')->latest()->first(),
            'gates' => EstateGate::forEstate($estate)->where('is_active', true)->get(),
            'awaiting' => Visit::forEstate($estate)->where('status', VisitStatus::AwaitingHost->value)->with('property')->latest()->get(),
        ]);
    }

    public function expected(): View
    {
        $estate = $this->context->estate();
        $tz = $estate->timezone();
        $start = now($tz)->startOfDay()->utc();
        $end = now($tz)->endOfDay()->utc();

        $passes = VisitorPass::forEstate($estate)->with('property', 'host')
            ->where('status', PassStatus::Active->value)
            ->where('valid_from', '<=', $end)->where('valid_until', '>=', now())
            ->orderBy('valid_from')->get();

        return view('gate.expected', compact('passes'));
    }

    public function inside(Request $request): View
    {
        $estate = $this->context->estate();
        $search = trim((string) $request->query('q'));

        $visits = Visit::forEstate($estate)->with('property', 'gate')
            ->where('status', VisitStatus::Inside->value)
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('visitor_name', 'like', "%{$search}%")->orWhere('vehicle_plate', 'like', "%{$search}%")))
            ->latest('checked_in_at')->get();

        return view('gate.inside', compact('visits', 'search'));
    }

    public function createWalkIn(): View
    {
        $estate = $this->context->estate();

        return view('gate.walk-in', [
            'properties' => Property::forEstate($estate)->orderBy('identifier')->get(['id', 'identifier']),
            'categories' => $estate->setting('visitors.pass_categories'),
            'needsApproval' => (bool) $estate->setting('visitors.require_walk_in_approval'),
        ]);
    }

    public function storeWalkIn(Request $request, GateService $gate): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'visitor_name' => ['required', 'string', 'max:120'],
            'visitor_phone' => ['nullable', 'string', 'max:20'],
            'category' => ['required', 'string', 'in:'.implode(',', $estate->setting('visitors.pass_categories'))],
            'purpose' => ['nullable', 'string', 'max:160'],
            'vehicle_plate' => ['nullable', 'string', 'max:15', 'regex:/^[A-Za-z0-9 -]+$/'],
            'group_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $visit = $gate->registerWalkIn($estate, $request->user(), $data, $this->currentGate($request));

        return redirect()->route('gate.index')->with('success', $visit->status === VisitStatus::AwaitingHost
            ? "Waiting for the resident to approve {$visit->visitor_name}."
            : "{$visit->visitor_name} checked in.");
    }

    public function checkOut(Visit $visit, Request $request, GateService $gate): RedirectResponse
    {
        $gate->checkOut($visit, $request->user());

        return back()->with('success', "{$visit->visitor_name} checked out.");
    }

    public function override(Visit $visit, Request $request, GateService $gate): RedirectResponse
    {
        $gate->admitAwaiting($visit, $request->user());

        return back()->with('success', "{$visit->visitor_name} admitted by supervisor override.");
    }

    public function shift(Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $open = SecurityShift::forEstate($estate)->where('user_id', $request->user()->id)->whereNull('ended_at')->first();

        if ($open) {
            $data = $request->validate(['handover_notes' => ['nullable', 'string', 'max:2000']]);
            $open->update(['ended_at' => now(), 'handover_notes' => $data['handover_notes'] ?? null]);

            return back()->with('success', 'Shift ended. Handover notes saved.');
        }

        $data = $request->validate(['gate_id' => ['nullable', 'integer']]);
        $gateId = isset($data['gate_id']) ? EstateGate::forEstate($estate)->whereKey($data['gate_id'])->value('id') : null;

        SecurityShift::create(['estate_id' => $estate->id, 'user_id' => $request->user()->id, 'gate_id' => $gateId, 'started_at' => now()]);

        return back()->with('success', 'Shift started.');
    }

    private function currentGate(Request $request): ?EstateGate
    {
        return SecurityShift::forEstate($this->context->estate())->where('user_id', $request->user()->id)->whereNull('ended_at')->latest()->first()?->gate;
    }
}
