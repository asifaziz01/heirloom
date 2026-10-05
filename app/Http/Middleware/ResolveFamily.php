<?php

namespace App\Http\Middleware;

use App\Models\Family;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the {family} route parameter, checks membership and minimum role,
 * and shares $family / $member with every view.
 *
 * Usage: ->middleware('family') or ->middleware('family:editor')
 */
class ResolveFamily
{
    public function handle(Request $request, Closure $next, string $minRole = 'viewer'): Response
    {
        $family = $request->route('family');
        abort_unless($family instanceof Family, 404);

        $member = $request->user()->membershipIn($family);
        abort_unless($member, 403, 'You are not a member of this family archive.');
        abort_unless($member->atLeast($minRole), 403, 'Your role in this family does not allow this action.');

        $member->setRelation('family', $family);
        if ($request->user()->current_family_id !== $family->id) {
            $request->user()->forceFill(['current_family_id' => $family->id])->save();
        }

        app()->instance(Family::class, $family);
        app()->instance('member', $member);
        View::share(['family' => $family, 'member' => $member]);

        return $next($request);
    }
}
