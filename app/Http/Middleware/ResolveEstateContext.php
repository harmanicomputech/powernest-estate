<?php

namespace App\Http\Middleware;

use App\Models\Estate;
use App\Support\EstateContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the estate the user is working in from the session (never from request input)
 * and verifies an active membership. With the "required" argument, users without a usable
 * estate are sent to onboarding.
 */
class ResolveEstateContext
{
    public function __construct(private EstateContext $context) {}

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $user = $request->user();

        if ($user) {
            $estate = $this->resolve($request);

            if ($estate) {
                $this->context->set($user, $estate);
                View::share('currentEstate', $estate);
            }
        }

        if ($mode === 'required' && ! $this->context->hasEstate()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No active estate membership.'], 403)
                : redirect()->route('onboarding');
        }

        return $next($request);
    }

    private function resolve(Request $request): ?Estate
    {
        $user = $request->user();
        $candidates = array_filter([$request->session()->get('estate_id'), $user->current_estate_id]);

        foreach ($candidates as $estateId) {
            $estate = Estate::find($estateId);

            if ($estate && $estate->isOperational() && $user->isMemberOf($estate)) {
                $request->session()->put('estate_id', $estate->id);

                return $estate;
            }
        }

        $estate = $user->estates()->orderBy('estates.name')->first();

        if ($estate) {
            $request->session()->put('estate_id', $estate->id);
            $user->forceFill(['current_estate_id' => $estate->id])->saveQuietly();
        }

        return $estate;
    }
}
