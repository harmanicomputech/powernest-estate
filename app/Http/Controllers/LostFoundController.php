<?php

namespace App\Http\Controllers;

use App\Models\LostFoundItem;
use App\Services\Operations\LostFoundService;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LostFoundController extends Controller
{
    public function __construct(private EstateContext $context, private LostFoundService $service) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');

        return view('lost-found.index', [
            'items' => LostFoundItem::forEstate($this->context->estate())->with('reporter')->where('status', $status === 'resolved' ? 'resolved' : 'open')->latest()->paginate(24)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['lost', 'found'])],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:160'],
        ]);

        $this->service->report($request->user(), $this->context->estate(), $data);

        return back()->with('success', $data['type'] === 'found' ? 'Thanks — the item is now listed for its owner.' : 'Your lost item has been posted.');
    }

    public function resolve(LostFoundItem $item, Request $request): RedirectResponse
    {
        $this->service->resolve($item, $request->user());

        return back()->with('success', 'Marked as returned.');
    }
}
