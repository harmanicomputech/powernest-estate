<?php

namespace App\Http\Controllers;

use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Models\VisitorPass;
use App\Services\Visitors\GateService;
use App\Services\Visitors\PassService;
use App\Support\EstateContext;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VisitorController extends Controller
{
    public function __construct(private EstateContext $context) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $estate = $this->context->estate();
        $tab = $request->query('tab', 'upcoming');

        $base = VisitorPass::forEstate($estate)->where('host_user_id', $user->id)->with('property');

        $passes = match ($tab) {
            'history' => (clone $base)->where(fn ($q) => $q->where('status', '!=', PassStatus::Active->value)->orWhere('valid_until', '<', now()))->latest('valid_from')->paginate(20),
            default => (clone $base)->where('status', PassStatus::Active->value)->where('valid_until', '>=', now())->orderBy('valid_from')->paginate(20),
        };

        $awaiting = Visit::forEstate($estate)->where('status', VisitStatus::AwaitingHost->value)
            ->whereIn('property_id', $user->authorizedProperties($estate)->pluck('properties.id'))
            ->latest()->get();

        $recentVisits = $tab === 'history'
            ? Visit::forEstate($estate)->whereIn('property_id', $user->authorizedProperties($estate)->pluck('properties.id'))->whereNotNull('checked_in_at')->latest('checked_in_at')->limit(10)->get()
            : collect();

        return view('visitors.index', compact('passes', 'tab', 'awaiting', 'recentVisits'));
    }

    public function create(Request $request): View
    {
        $estate = $this->context->estate();

        return view('visitors.create', [
            'properties' => $request->user()->authorizedProperties($estate)->orderBy('identifier')->get(),
            'categories' => $estate->setting('visitors.pass_categories'),
            'maxDays' => $estate->setting('visitors.max_pass_days'),
            'now' => now()->timezone($estate->timezone()),
        ]);
    }

    public function store(Request $request, PassService $passes): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'visitor_name' => ['required', 'string', 'max:120'],
            'visitor_phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,20}$/'],
            'visitor_email' => ['nullable', 'email', 'max:160'],
            'category' => ['required', 'string', 'max:30'],
            'pass_type' => ['required', Rule::enum(PassType::class)],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date'],
            'group_size' => ['nullable', 'integer', 'min:1', 'max:50'],
            'vehicle_plate' => ['nullable', 'string', 'max:15', 'regex:/^[A-Za-z0-9 -]+$/'],
            'max_entries' => ['nullable', 'integer', 'min:1', 'max:100'],
            'days' => ['nullable', 'array'],
            'days.*' => ['integer', 'between:1,7'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $pass = $passes->issue($request->user(), $this->context->estate(), $data);

        return redirect()->route('visitors.show', $pass)->with('success', 'Pass created. Share it with your visitor.');
    }

    public function show(VisitorPass $pass): View
    {
        Gate::authorize('view', $pass);

        return view('visitors.show', [
            'pass' => $pass->load('property', 'host', 'visits.gate'),
            'shareUrl' => route('passes.public', $pass->token()),
        ]);
    }

    public function qr(VisitorPass $pass): Response
    {
        Gate::authorize('view', $pass);

        return response(self::qrSvg(PassService::qrPayload($pass)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function revoke(VisitorPass $pass, Request $request, PassService $passes): RedirectResponse
    {
        Gate::authorize('revoke', $pass);
        $passes->revoke($pass, $request->user());

        return redirect()->route('visitors.show', $pass)->with('success', 'Pass revoked. It will be refused at the gate.');
    }

    public function walkIn(Visit $visit, Request $request): View
    {
        abort_unless($request->user()->authorizedProperties($visit->estate_id)->whereKey($visit->property_id)->exists(), 403);

        return view('visitors.walk-in', ['visit' => $visit->load('property', 'gate')]);
    }

    public function respond(Visit $visit, Request $request, GateService $gate): RedirectResponse
    {
        $request->validate(['decision' => ['required', Rule::in(['approve', 'decline'])]]);
        $gate->respondToWalkIn($visit, $request->user(), $request->input('decision') === 'approve');

        return redirect()->route('visitors.index')->with('success', $request->input('decision') === 'approve' ? "Security has been told to let {$visit->visitor_name} in." : 'Visitor declined. Security has been informed.');
    }

    public static function qrSvg(string $payload): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'svgUseFillAttributes' => true,
            'moduleValues' => [],
            'drawLightModules' => false,
            'connectPaths' => true,
        ]);

        return (new QRCode($options))->render($payload);
    }
}
