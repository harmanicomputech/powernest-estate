<?php

namespace App\Enums;

/**
 * Estate-scoped permissions. Platform administration is separate (users.is_platform_admin).
 */
enum Permission: string
{
    case EstateSettings = 'estate.settings';
    case PropertiesView = 'properties.view';
    case PropertiesManage = 'properties.manage';
    case ResidentsView = 'residents.view';
    case ResidentsManage = 'residents.manage';
    case MembersManage = 'members.manage';
    case DirectoryView = 'directory.view';

    case VisitorsInvite = 'visitors.invite';
    case VisitorsViewAll = 'visitors.view_all';
    case GateOperate = 'gate.operate';
    case GateSupervise = 'gate.supervise';

    case IncidentsReport = 'incidents.report';
    case IncidentsManage = 'incidents.manage';
    case InspectionsPerform = 'inspections.perform';

    case PowerPurchase = 'power.purchase';
    case PowerViewAll = 'power.view_all';
    case PowerReconcile = 'power.reconcile';
    case PowerVendManual = 'power.vend_manual';

    case BillingViewOwn = 'billing.view_own';
    case BillingManage = 'billing.manage';
    case BillingApprove = 'billing.approve';
    case ExpensesManage = 'expenses.manage';
    case ExpensesApprove = 'expenses.approve';

    case MaintenanceRequest = 'maintenance.request';
    case MaintenanceManage = 'maintenance.manage';
    case MaintenanceWork = 'maintenance.work';

    case FacilitiesBook = 'facilities.book';
    case FacilitiesManage = 'facilities.manage';

    case AnnouncementsPublish = 'announcements.publish';
    case StaffManage = 'staff.manage';
    case TasksWork = 'tasks.work';
    case SupportManage = 'support.manage';
    case ReportsView = 'reports.view';
    case AuditView = 'audit.view';
    case RolesManage = 'roles.manage';

    public function label(): string
    {
        return match ($this) {
            self::EstateSettings => 'Change estate settings',
            self::PropertiesView => 'View properties',
            self::PropertiesManage => 'Add and edit properties',
            self::ResidentsView => 'View residents',
            self::ResidentsManage => 'Manage residents and move-ins',
            self::MembersManage => 'Invite staff and approve access requests',
            self::DirectoryView => 'See the resident directory',
            self::VisitorsInvite => 'Invite visitors',
            self::VisitorsViewAll => 'View the visitor log',
            self::GateOperate => 'Operate the gate console',
            self::GateSupervise => 'Supervise gate operations',
            self::IncidentsReport => 'Report incidents and lost items',
            self::IncidentsManage => 'Manage incidents',
            self::InspectionsPerform => 'Carry out inspections',
            self::PowerPurchase => 'Buy electricity',
            self::PowerViewAll => 'View all electricity purchases',
            self::PowerReconcile => 'Resolve failed electricity purchases',
            self::PowerVendManual => 'Issue electricity tokens without payment',
            self::BillingViewOwn => 'See own bills',
            self::BillingManage => 'Manage billing and verify receipts',
            self::BillingApprove => 'Approve billing adjustments and reversals',
            self::ExpensesManage => 'Record expenses',
            self::ExpensesApprove => 'Approve expenses',
            self::MaintenanceRequest => 'Request maintenance',
            self::MaintenanceManage => 'Manage work orders and assets',
            self::MaintenanceWork => 'Work on assigned jobs',
            self::FacilitiesBook => 'Book facilities',
            self::FacilitiesManage => 'Manage facilities and bookings',
            self::AnnouncementsPublish => 'Publish announcements',
            self::StaffManage => 'Assign tasks to staff',
            self::TasksWork => 'Work on staff tasks',
            self::SupportManage => 'Handle support requests',
            self::ReportsView => 'View and download reports',
            self::AuditView => 'View the audit log',
            self::RolesManage => 'Manage roles and permissions',
        };
    }

    public function group(): string
    {
        return match (explode('.', $this->value)[0]) {
            'estate', 'members', 'roles', 'staff', 'audit' => 'Administration',
            'properties', 'residents', 'directory' => 'Properties & residents',
            'visitors', 'gate' => 'Visitors & gate',
            'incidents', 'inspections' => 'Safety & security',
            'power' => 'Electricity',
            'billing', 'expenses' => 'Finance',
            'maintenance', 'facilities' => 'Maintenance & facilities',
            default => 'Communication & reports',
        };
    }
}
