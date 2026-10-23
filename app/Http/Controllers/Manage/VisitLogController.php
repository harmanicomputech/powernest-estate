<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Support\EstateContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class VisitLogController extends Controller
{
    public function index(Request $request, EstateContext $context): View
    {
        $estate = $context->estate();
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');
        $date = $request->query('date');

        $visits = Visit::forEstate($estate)->with('property', 'gate', 'checkedInBy', 'host')
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('visitor_name', 'like', "%{$search}%")->orWhere('vehicle_plate', 'like', "%{$search}%")->orWhere('visitor_phone', 'like', "%{$search}%")->orWhereHas('property', fn ($p) => $p->where('identifier', 'like', "%{$search}%"))))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($date, fn ($q) => $q->whereBetween('created_at', [Carbon::parse($date, $estate->timezone())->startOfDay()->utc(), Carbon::parse($date, $estate->timezone())->endOfDay()->utc()]))
            ->latest()->paginate(40)->withQueryString();

        return view('manage.visits.index', compact('visits', 'search', 'status', 'date'));
    }
}
