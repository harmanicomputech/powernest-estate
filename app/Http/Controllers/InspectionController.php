<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Services\Operations\InspectionService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InspectionController extends Controller
{
    public const CATEGORIES = ['security' => 'Security', 'fire_safety' => 'Fire safety', 'electrical' => 'Electrical', 'facility' => 'Facility', 'hygiene' => 'Hygiene'];

    public function __construct(private EstateContext $context, private InspectionService $service) {}

    public function index(): View
    {
        $estate = $this->context->estate();

        return view('inspections.index', [
            'templates' => InspectionTemplate::forEstate($estate)->where('is_active', true)->withCount('inspections')->orderBy('name')->get(),
            'inProgress' => Inspection::forEstate($estate)->where('status', 'in_progress')->with('inspector')->latest()->get(),
            'completed' => Inspection::forEstate($estate)->where('status', 'completed')->with('inspector')->latest('completed_at')->paginate(20),
            'canManage' => $this->canManageTemplates(),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        abort_unless($this->canManageTemplates(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'items' => ['required', 'string', 'max:5000'],
        ]);

        $this->service->createTemplate($this->context->estate(), $request->user(), $data['name'], $data['category'], preg_split('/\R/', $data['items']));

        return back()->with('success', 'Checklist saved.');
    }

    public function start(InspectionTemplate $template, Request $request): RedirectResponse
    {
        $data = $request->validate(['location' => ['nullable', 'string', 'max:160']]);
        $inspection = $this->service->start($template, $request->user(), $data['location'] ?? null);

        return redirect()->route('inspections.show', $inspection);
    }

    public function show(Inspection $inspection): View
    {
        return view('inspections.show', ['inspection' => $inspection->load('inspector', 'template')]);
    }

    public function complete(Inspection $inspection, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.result' => ['nullable', Rule::in(InspectionService::RESULTS)],
            'answers.*.note' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'raise_maintenance' => ['nullable', 'boolean'],
        ]);

        $inspection = $this->service->complete($inspection, $request->user(), $data['answers'], $data['notes'] ?? null, $request->boolean('raise_maintenance'));

        return redirect()->route('inspections.show', $inspection)->with('success', $inspection->failed_count ? "Inspection completed with {$inspection->failed_count} failed item(s)." : 'Inspection completed — all clear.');
    }

    private function canManageTemplates(): bool
    {
        return Gate::any([Permission::GateSupervise->value, Permission::MaintenanceManage->value]);
    }
}
