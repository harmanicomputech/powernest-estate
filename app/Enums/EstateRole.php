<?php

namespace App\Enums;

use App\Enums\Permission as P;

enum EstateRole: string
{
    case EstateOwner = 'estate_owner';
    case EstateManager = 'estate_manager';
    case EstateAdmin = 'estate_admin';
    case FinanceOfficer = 'finance_officer';
    case SecuritySupervisor = 'security_supervisor';
    case SecurityOfficer = 'security_officer';
    case FacilityManager = 'facility_manager';
    case Technician = 'technician';
    case Landlord = 'landlord';
    case Resident = 'resident';
    case HouseholdMember = 'household_member';
    case Contractor = 'contractor';

    public function label(): string
    {
        return match ($this) {
            self::EstateOwner => 'Estate Owner',
            self::EstateManager => 'Estate Manager',
            self::EstateAdmin => 'Estate Administrator',
            self::FinanceOfficer => 'Finance Officer',
            self::SecuritySupervisor => 'Security Supervisor',
            self::SecurityOfficer => 'Security Officer',
            self::FacilityManager => 'Facility Manager',
            self::Technician => 'Technician',
            self::Landlord => 'Landlord',
            self::Resident => 'Resident',
            self::HouseholdMember => 'Household Member',
            self::Contractor => 'Contractor',
        };
    }

    /**
     * Roles held by people who live in or own property, as opposed to staff.
     */
    public function isCommunity(): bool
    {
        return in_array($this, [self::Landlord, self::Resident, self::HouseholdMember], true);
    }

    public function isStaff(): bool
    {
        return ! $this->isCommunity() && $this !== self::Contractor;
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        $community = [P::VisitorsInvite, P::PowerPurchase, P::BillingViewOwn, P::MaintenanceRequest, P::FacilitiesBook, P::IncidentsReport];

        return match ($this) {
            self::EstateOwner, self::EstateManager => P::cases(),
            self::EstateAdmin => array_values(array_filter(P::cases(), fn (P $p) => ! in_array($p, [P::EstateSettings, P::BillingApprove, P::ExpensesApprove], true))),
            self::FinanceOfficer => [P::PropertiesView, P::ResidentsView, P::PowerViewAll, P::PowerReconcile, P::BillingManage, P::BillingApprove, P::ExpensesManage, P::ExpensesApprove, P::SupportManage, P::ReportsView, P::TasksWork],
            self::SecuritySupervisor => [P::PropertiesView, P::ResidentsView, P::VisitorsViewAll, P::GateOperate, P::GateSupervise, P::IncidentsReport, P::IncidentsManage, P::StaffManage, P::TasksWork, P::ReportsView],
            self::SecurityOfficer => [P::PropertiesView, P::GateOperate, P::IncidentsReport, P::TasksWork],
            self::FacilityManager => [P::PropertiesView, P::ResidentsView, P::MaintenanceManage, P::MaintenanceWork, P::FacilitiesManage, P::ExpensesManage, P::StaffManage, P::TasksWork, P::ReportsView, P::IncidentsReport],
            self::Technician => [P::MaintenanceWork, P::TasksWork, P::IncidentsReport],
            self::Landlord => [...$community],
            self::Resident => [...$community],
            self::HouseholdMember => [P::VisitorsInvite, P::PowerPurchase, P::MaintenanceRequest, P::FacilitiesBook, P::IncidentsReport],
            self::Contractor => [P::MaintenanceWork],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
