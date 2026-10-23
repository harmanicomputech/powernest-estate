<?php

namespace App\Http\Controllers;

use App\Services\Estate\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(Request $request, string $token, MembershipService $members): View
    {
        $invitation = $members->findInvitation($token);
        abort_unless($invitation, 404);

        if (! $request->user()) {
            // Let registration pre-fill the invited email, and return here afterwards.
            $request->session()->put('invitation_email', $invitation->email);
            $request->session()->put('url.intended', route('invitations.show', $token));
        }

        return view('invitations.show', [
            'invitation' => $invitation,
            'token' => $token,
            'problem' => $members->invitationProblem($invitation),
        ]);
    }

    public function accept(Request $request, string $token, MembershipService $members): RedirectResponse
    {
        $invitation = $members->findInvitation($token);
        abort_unless($invitation, 404);

        $members->accept($invitation, $request->user());
        $request->session()->put('estate_id', $invitation->estate_id);

        return redirect()->route('dashboard')->with('success', "Welcome to {$invitation->estate->name}!");
    }
}
