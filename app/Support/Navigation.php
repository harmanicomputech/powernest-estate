<?php

namespace App\Support;

use App\Enums\Permission as P;
use App\Models\User;

/**
 * Builds the navigation for the current user from their permissions in the current estate,
 * so every role sees a menu shaped around its own work.
 */
class Navigation
{
    public function __construct(private EstateContext $context) {}

    /**
     * Sidebar sections for desktop / the mobile "more" sheet.
     *
     * @return list<array{label: string, items: list<array{label: string, route: string, icon: string, active: string}>}>
     */
    public function sections(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $can = fn (P ...$permissions) => collect($permissions)->contains(fn (P $p) => $this->context->can($p));
        $item = fn (string $label, string $route, string $icon, ?string $active = null) => ['label' => $label, 'route' => $route, 'icon' => $icon, 'active' => $active ?? $route.'*'];

        $sections = [];

        if ($this->context->hasEstate()) {
            $sections[] = ['label' => 'Overview', 'items' => array_values(array_filter([
                $item('Dashboard', 'dashboard', 'layout-dashboard', 'dashboard'),
                $this->context->isCommunity() ? $item('My home', 'household.index', 'house', 'household.*') : null,
                $item('Announcements', 'announcements.index', 'megaphone', 'announcements.*'),
            ]))];

            $community = array_values(array_filter([
                $can(P::VisitorsInvite) ? $item('Visitors', 'visitors.index', 'user-plus', 'visitors.*') : null,
                $can(P::PowerPurchase) ? $item('Electricity', 'power.index', 'zap', 'power.*') : null,
                $can(P::BillingViewOwn) ? $item('Bills & payments', 'bills.index', 'receipt', 'bills.*') : null,
                $can(P::MaintenanceRequest) && ! $can(P::MaintenanceManage) ? $item('Maintenance', 'maintenance.index', 'wrench', 'maintenance.*') : null,
                $can(P::FacilitiesBook) ? $item('Facilities', 'facilities.index', 'calendar', 'facilities.*') : null,
            ]));

            if ($community) {
                $sections[] = ['label' => 'Services', 'items' => $community];
            }

            $operations = array_values(array_filter([
                $can(P::GateOperate) ? $item('Gate console', 'gate.index', 'scan-line', 'gate.*') : null,
                $can(P::VisitorsViewAll) ? $item('Visitor log', 'manage.visits.index', 'door-open', 'manage.visits.*') : null,
                $can(P::IncidentsReport, P::IncidentsManage) ? $item('Incidents', 'incidents.index', 'siren', 'incidents.*') : null,
                $can(P::MaintenanceManage, P::MaintenanceWork) ? $item('Work orders', 'maintenance.index', 'hammer', 'maintenance.*') : null,
                $can(P::MaintenanceManage) ? $item('Assets', 'manage.assets.index', 'plug-zap', 'manage.assets.*') : null,
                $can(P::TasksWork) ? $item('Tasks', 'tasks.index', 'clipboard-list', 'tasks.*') : null,
            ]));

            if ($operations) {
                $sections[] = ['label' => 'Operations', 'items' => $operations];
            }

            $estate = array_values(array_filter([
                $can(P::PropertiesView) ? $item('Properties', 'manage.properties.index', 'building-2', 'manage.properties.*') : null,
                $can(P::ResidentsView) ? $item('Residents', 'manage.residents.index', 'users', 'manage.residents.*') : null,
                $can(P::MembersManage) ? $item('Members & access', 'manage.members.index', 'key-round', 'manage.members.*') : null,
                $can(P::PowerViewAll) ? $item('Power operations', 'manage.power.index', 'plug-zap', 'manage.power.*') : null,
                $can(P::BillingManage) ? $item('Billing', 'manage.billing.index', 'landmark', 'manage.billing.*') : null,
                $can(P::ExpensesManage) ? $item('Expenses', 'manage.expenses.index', 'wallet', 'manage.expenses.*') : null,
                $can(P::FacilitiesManage) ? $item('Bookings', 'manage.bookings.index', 'calendar-check', 'manage.bookings.*') : null,
                $can(P::SupportManage) ? $item('Support', 'manage.support.index', 'life-buoy', 'manage.support.*') : null,
                $can(P::ReportsView) ? $item('Reports', 'manage.reports.index', 'chart-column', 'manage.reports.*') : null,
                $can(P::AuditView) ? $item('Audit log', 'manage.audit.index', 'history', 'manage.audit.*') : null,
                $can(P::EstateSettings) ? $item('Estate settings', 'manage.settings.edit', 'settings', 'manage.settings.*') : null,
            ]));

            if ($estate) {
                $sections[] = ['label' => 'Estate', 'items' => $estate];
            }
        }

        if ($user->isPlatformAdmin()) {
            $sections[] = ['label' => 'Platform', 'items' => [
                $item('Platform console', 'platform.dashboard', 'layers', 'platform.*'),
            ]];
        }

        return $sections;
    }

    /**
     * Thumb-reach bottom bar (max five) for phones, shaped by the user's primary job.
     *
     * @return list<array{label: string, route: string, icon: string, active: string, primary?: bool}>
     */
    public function bottomBar(?User $user): array
    {
        if (! $user || ! $this->context->hasEstate()) {
            return [];
        }

        $can = fn (P $p) => $this->context->can($p);

        if ($can(P::GateOperate) && ! $this->context->isCommunity()) {
            return array_values(array_filter([
                ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'layout-dashboard', 'active' => 'dashboard'],
                ['label' => 'Expected', 'route' => 'gate.expected', 'icon' => 'clock', 'active' => 'gate.expected'],
                ['label' => 'Scan', 'route' => 'gate.index', 'icon' => 'scan-line', 'active' => 'gate.index', 'primary' => true],
                ['label' => 'Inside', 'route' => 'gate.inside', 'icon' => 'door-open', 'active' => 'gate.inside'],
                ['label' => 'Incidents', 'route' => 'incidents.index', 'icon' => 'siren', 'active' => 'incidents.*'],
            ]));
        }

        if ($this->context->isCommunity()) {
            return array_values(array_filter([
                ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'house', 'active' => 'dashboard'],
                $can(P::VisitorsInvite) ? ['label' => 'Visitors', 'route' => 'visitors.index', 'icon' => 'user-plus', 'active' => 'visitors.*'] : null,
                $can(P::PowerPurchase) ? ['label' => 'Power', 'route' => 'power.index', 'icon' => 'zap', 'active' => 'power.*', 'primary' => true] : null,
                $can(P::BillingViewOwn) ? ['label' => 'Bills', 'route' => 'bills.index', 'icon' => 'receipt', 'active' => 'bills.*'] : ['label' => 'Requests', 'route' => 'maintenance.index', 'icon' => 'wrench', 'active' => 'maintenance.*'],
            ]));
        }

        return array_values(array_filter([
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'layout-dashboard', 'active' => 'dashboard'],
            $can(P::MaintenanceManage) || $can(P::MaintenanceWork) ? ['label' => 'Work', 'route' => 'maintenance.index', 'icon' => 'hammer', 'active' => 'maintenance.*'] : null,
            $can(P::TasksWork) ? ['label' => 'Tasks', 'route' => 'tasks.index', 'icon' => 'clipboard-list', 'active' => 'tasks.*'] : null,
            $can(P::BillingManage) ? ['label' => 'Billing', 'route' => 'manage.billing.index', 'icon' => 'landmark', 'active' => 'manage.billing.*'] : null,
        ]));
    }
}
