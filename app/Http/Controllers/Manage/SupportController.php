<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(Request $request, EstateContext $context): View
    {
        $status = $request->query('status', 'open');

        return view('manage.support.index', [
            'tickets' => SupportTicket::forEstate($context->estate())->with('user', 'purchase', 'messages.user')->where('status', $status)->latest()->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function reply(SupportTicket $ticket, Request $request): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'resolve' => ['nullable', 'boolean']]);
        $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body'], 'is_staff' => true]);

        if ($request->boolean('resolve')) {
            $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
        }

        return back()->with('success', 'Reply saved.');
    }
}
