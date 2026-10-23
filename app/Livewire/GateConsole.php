<?php

namespace App\Livewire;

use App\Models\SecurityShift;
use App\Models\VisitorPass;
use App\Services\Visitors\GateService;
use App\Support\EstateContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Security officer console. The browser sends only the scanned/typed value; the server decides.
 * The pass id held between "check" and "admit" is re-validated under a row lock on admit.
 */
class GateConsole extends Component
{
    public string $code = '';

    public ?int $passId = null;

    public ?string $result = null;

    public ?string $message = null;

    public bool $valid = false;

    public ?string $vehiclePlate = null;

    public ?int $groupSize = null;

    public string $denyReason = '';

    public ?string $admitted = null;

    #[On('pass-scanned')]
    public function scanned(string $value): void
    {
        $this->code = $value;
        $this->check();
    }

    public function check(): void
    {
        $this->resetResult();
        $key = 'gate-check:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->result = 'throttled';
            $this->message = 'Too many checks. Wait a moment and try again.';

            return;
        }
        RateLimiter::hit($key, 60);

        $value = trim($this->code);
        if ($value === '') {
            return;
        }

        $context = app(EstateContext::class);
        $check = app(GateService::class)->check($context->estate(), Auth::user(), $value, $this->gate());

        $this->valid = $check->valid;
        $this->result = $check->result;
        $this->message = $check->message;
        $this->passId = $check->pass?->id;

        if ($check->pass) {
            $this->vehiclePlate = $check->pass->vehicle_plate;
            $this->groupSize = $check->pass->group_size;
        }
    }

    public function admit(): void
    {
        $this->validate([
            'vehiclePlate' => ['nullable', 'string', 'max:15', 'regex:/^[A-Za-z0-9 -]+$/'],
            'groupSize' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $pass = $this->pass();

        try {
            $visit = app(GateService::class)->admit($pass, Auth::user(), $this->gate(), ['vehicle_plate' => $this->vehiclePlate, 'group_size' => $this->groupSize]);
        } catch (ValidationException $e) {
            $this->valid = false;
            $this->result = 'rejected';
            $this->message = collect($e->errors())->flatten()->first();

            return;
        }

        $name = $visit->visitor_name;
        $this->reset();
        $this->admitted = $name;
        $this->dispatch('toast', tone: 'success', message: "{$name} admitted.");
    }

    public function deny(): void
    {
        $this->validate(['denyReason' => ['required', 'string', 'max:160']]);

        app(GateService::class)->deny(app(EstateContext::class)->estate(), Auth::user(), $this->denyReason, $this->passId ? $this->pass() : null);
        $this->reset();
        $this->dispatch('toast', tone: 'warning', message: 'Entry denied and recorded.');
    }

    public function clear(): void
    {
        $this->reset();
    }

    public function render(): View
    {
        return view('livewire.gate-console', [
            'pass' => $this->passId ? VisitorPass::with('property', 'host')->find($this->passId) : null,
        ]);
    }

    private function pass(): VisitorPass
    {
        $estate = app(EstateContext::class)->estate();

        return VisitorPass::where('estate_id', $estate->id)->findOrFail($this->passId);
    }

    private function gate(): ?\App\Models\Gate
    {
        return SecurityShift::where('estate_id', app(EstateContext::class)->estate()->id)->where('user_id', Auth::id())->whereNull('ended_at')->latest()->first()?->gate;
    }

    private function resetResult(): void
    {
        $this->reset('passId', 'result', 'message', 'valid', 'vehiclePlate', 'groupSize', 'denyReason', 'admitted');
    }
}
