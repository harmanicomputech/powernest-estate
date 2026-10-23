<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\EstateContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request, EstateContext $context): View
    {
        $action = trim((string) $request->query('action'));

        return view('manage.audit.index', [
            'logs' => AuditLog::where('estate_id', $context->estate()->id)->with('user')
                ->when($action, fn ($q) => $q->where('action', 'like', "{$action}%"))
                ->latest('id')->paginate(50)->withQueryString(),
            'action' => $action,
        ]);
    }
}
