<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Integrations\Payments\GatewayManager;
use App\Models\ChargeType;
use App\Models\EmergencyContact;
use App\Models\Gate;
use App\Services\Audit\AuditLogger;
use App\Support\EstateContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private EstateContext $context, private AuditLogger $audit) {}

    public function edit(GatewayManager $gateways): View
    {
        $estate = $this->context->estate();

        return view('manage.settings.edit', [
            'estate' => $estate,
            'gates' => Gate::forEstate($estate)->get(),
            'contacts' => EmergencyContact::forEstate($estate)->orderBy('sort_order')->get(),
            'platformGateways' => ['payaza' => $gateways->get('payaza'), 'paystack' => $gateways->get('paystack')],
            'allCategories' => config('powernest.estate_defaults.visitors.pass_categories'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'timezone' => ['required', 'timezone'],
            'service_charge' => ['required', 'string', 'max:15'],
            'min_amount' => ['required', 'string', 'max:15'],
            'max_amount' => ['required', 'string', 'max:15'],
            'gateways' => ['nullable', 'array'],
            'gateways.*' => [Rule::in(['payaza', 'paystack'])],
            'pass_categories' => ['required', 'array', 'min:1'],
            'pass_categories.*' => [Rule::in(config('powernest.estate_defaults.visitors.pass_categories'))],
            'max_pass_days' => ['required', 'integer', 'min:1', 'max:365'],
            'require_walk_in_approval' => ['nullable', 'boolean'],
            'directory_visible' => ['nullable', 'boolean'],
            'levy_amount' => ['required', 'string', 'max:15'],
            'levy_lockout_enabled' => ['nullable', 'boolean'],
            'levy_grace_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $min = Money::parse($data['min_amount']);
        $max = Money::parse($data['max_amount']);
        if ($min >= $max) {
            return back()->withInput()->withErrors(['max_amount' => 'Maximum must be greater than minimum.']);
        }

        $estate->fill(collect($data)->only('name', 'address', 'contact_email', 'contact_phone', 'timezone')->all());
        $estate->putSetting('power.service_charge', Money::parse($data['service_charge']));
        $estate->putSetting('power.min_amount', $min);
        $estate->putSetting('power.max_amount', $max);
        $estate->putSetting('power.gateways', array_values($data['gateways'] ?? []));
        $estate->putSetting('visitors.pass_categories', array_values($data['pass_categories']));
        $estate->putSetting('visitors.max_pass_days', (int) $data['max_pass_days']);
        $estate->putSetting('visitors.require_walk_in_approval', $request->boolean('require_walk_in_approval'));
        $estate->putSetting('residents.directory_visible', $request->boolean('directory_visible'));
        $estate->putSetting('levy.amount', Money::parse($data['levy_amount']));
        $estate->putSetting('levy.lockout_enabled', $request->boolean('levy_lockout_enabled'));
        $estate->putSetting('levy.grace_days', (int) $data['levy_grace_days']);
        $estate->save();

        // Keep the yearly levy charge in Billing in step with the amount set here.
        ChargeType::updateOrCreate(
            ['estate_id' => $estate->id, 'code' => 'LEVY'],
            ['name' => 'Estate management & security levy', 'amount' => (int) $estate->setting('levy.amount'), 'frequency' => 'annual', 'is_active' => true],
        );

        $this->audit->record('estate.settings_updated', $estate, ['changed' => array_keys($estate->getChanges())], $estate);

        return back()->with('success', 'Estate settings saved.');
    }

    public function storeGate(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        Gate::create($data + ['estate_id' => $this->context->estate()->id]);

        return back()->with('success', 'Gate added.');
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'role' => ['nullable', 'string', 'max:80'], 'phone' => ['required', 'string', 'max:20']]);
        EmergencyContact::create($data + ['estate_id' => $this->context->estate()->id]);

        return back()->with('success', 'Emergency contact added.');
    }
}
