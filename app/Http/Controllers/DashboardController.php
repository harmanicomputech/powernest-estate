<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Services\Dashboard\DashboardMetrics;
use App\Support\EstateContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One dashboard, composed from the panels relevant to the user's roles in the current estate.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, EstateContext $context, DashboardMetrics $metrics): View
    {
        $user = $request->user();
        $estate = $context->estate();

        $panels = [];

        if ($context->can(Permission::ReportsView) && $context->can(Permission::MembersManage)) {
            $panels['management'] = $metrics->management($estate);
        } elseif ($context->can(Permission::BillingManage)) {
            $panels['management'] = $metrics->management($estate);
        }

        if ($context->can(Permission::GateOperate)) {
            $panels['security'] = $metrics->security($estate);
        }

        if ($context->can(Permission::MaintenanceManage) || ($context->can(Permission::MaintenanceWork) && ! $context->isCommunity())) {
            $panels['facility'] = $metrics->facility($estate, $user);
        }

        if ($context->isCommunity()) {
            $panels['resident'] = $metrics->resident($user, $estate);
        }

        return view('dashboard.index', ['panels' => $panels, 'estate' => $estate, 'user' => $user]);
    }
}
