<?php

namespace App\Http\Controllers\Manage;

use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\Operations\MaintenanceService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function __construct(private EstateContext $context) {}

    public function index(): View
    {
        return view('manage.assets.index', [
            'assets' => Asset::forEstate($this->context->estate())->withCount(['maintenanceRequests' => fn ($q) => $q->whereNotIn('status', ['closed', 'cancelled'])])->orderByRaw('next_service_due_on IS NULL')->orderBy('next_service_due_on')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(['generator', 'transformer', 'borehole', 'pump', 'gate', 'cctv', 'lighting', 'elevator', 'other'])],
            'location' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:80'],
            'service_interval_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'last_serviced_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $next = ! empty($data['service_interval_days']) ? now()->parse($data['last_serviced_on'] ?? now())->addDays($data['service_interval_days'])->toDateString() : null;
        Asset::create($data + ['estate_id' => $this->context->estate()->id, 'next_service_due_on' => $next]);

        return back()->with('success', "{$data['name']} added.");
    }

    /**
     * Raises a preventive work order for the asset and rolls its next service date forward.
     */
    public function schedule(Asset $asset, Request $request, MaintenanceService $maintenance): RedirectResponse
    {
        $order = $maintenance->submit($request->user(), $this->context->estate(), [
            'category' => 'common_area',
            'location' => trim($asset->name.' · '.$asset->location, ' ·'),
            'title' => "Preventive service: {$asset->name}",
            'description' => "Scheduled preventive maintenance for {$asset->name}".($asset->serial_number ? " (S/N {$asset->serial_number})" : '').'.',
            'priority' => Priority::Normal->value,
        ]);
        $order->update(['asset_id' => $asset->id, 'is_preventive' => true]);

        $asset->update([
            'last_serviced_on' => now()->toDateString(),
            'next_service_due_on' => $asset->service_interval_days ? now()->addDays($asset->service_interval_days)->toDateString() : null,
        ]);

        return redirect()->route('maintenance.show', $order)->with('success', 'Preventive work order created.');
    }
}
