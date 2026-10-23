<?php

namespace App\Http\Controllers;

use App\Models\Estate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EstateSwitchController extends Controller
{
    public function __invoke(Request $request, Estate $estate): RedirectResponse
    {
        abort_unless($estate->isOperational() && $request->user()->isMemberOf($estate), 403);

        $request->session()->put('estate_id', $estate->id);
        $request->user()->forceFill(['current_estate_id' => $estate->id])->save();

        return redirect()->route('dashboard')->with('success', "Switched to {$estate->name}.");
    }
}
