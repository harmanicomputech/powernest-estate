<?php

namespace App\Http\Controllers;

use App\Models\VisitorPass;
use App\Services\Visitors\PassService;
use Illuminate\Http\Response;

class PublicPassController extends Controller
{
    public function show(string $token): Response
    {
        $pass = VisitorPass::with('estate', 'property', 'host')->where('token_lookup', PassService::tokenLookup($token))->first();
        abort_unless($pass && hash_equals($pass->token(), $token), 404);

        return response()->view('passes.public', ['pass' => $pass])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
