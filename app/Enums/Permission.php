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

    case PowerPurchase = 'power.purchase';
    case PowerViewAll = 'power.view_all';
    case PowerReconcile = 'power.reconcile';

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
}
