<?php

namespace App\Http\Controllers;

use App\Models\VisitorPass;
use App\Services\Visitors\PassService;
use Illuminate\View\View;

class PublicPassController extends Controller
{
    public function show(string $token): View
    {
        $pass = VisitorPass::with('estate', 'property', 'host')->where('token_lookup', PassService::tokenLookup($token))->first();
        abort_unless($pass && hash_equals($pass->token(), $token), 404);

        return view('passes.public', ['pass' => $pass]);
    }
}
