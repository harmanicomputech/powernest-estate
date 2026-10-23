<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Occupancy;
use App\Support\EstateContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResidentController extends Controller
{
    public function index(Request $request, EstateContext $context): View
    {
        $search = trim((string) $request->query('q'));
        $relationship = $request->query('relationship');

        $occupancies = Occupancy::forEstate($context->estate())->where('status', 'active')
            ->with('user', 'property.building')
            ->when($relationship, fn ($q) => $q->where('relationship', $relationship))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                ->orWhereHas('property', fn ($p) => $p->where('identifier', 'like', "%{$search}%"))))
            ->join('properties', 'properties.id', '=', 'occupancies.property_id')
            ->orderBy('properties.identifier')->select('occupancies.*')
            ->paginate(30)->withQueryString();

        return view('manage.residents.index', compact('occupancies', 'search', 'relationship'));
    }
}
