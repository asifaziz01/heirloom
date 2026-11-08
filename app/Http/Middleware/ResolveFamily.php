<?php

namespace App\Http\Middleware;

use App\Models\Family;
use App\Models\FamilyMember;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the {family} route parameter, checks membership and minimum role,
 * and shares $family / $member with every view.
 *
 * Members get their real membership. On a public archive, anyone else (a visitor
 * without an account, or a signed-in non-member) browses as a guest viewer; a guest
 * who reaches a route that needs a role is sent to the sign-in page and brought back.
 *
 * Usage: ->middleware('family') or ->middleware('family:editor')
 */
class ResolveFamily
{
    public function handle(Request $request, Closure $next, string $minRole = 'viewer'): Response
    {
        $family = $request->route('family');
        abort_unless($family instanceof Family, 404);

        $user = $request->user();
        $member = $user?->membershipIn($family);

        if (! $member) {
            if (! $family->is_public) {
                if (! $user) {
                    throw new AuthenticationException;
                }
                abort(403, 'You are not a member of this family archive.');
            }
            if ($minRole !== 'viewer') {
                if (! $user) {
                    throw new AuthenticationException;
                }
                abort(403, 'Only members of this family can do that. Ask an admin for an invitation.');
            }
            $member = FamilyMember::guest($family);
        } else {
            abort_unless($member->atLeast($minRole), 403, 'Your role in this family does not allow this action.');
            $member->setRelation('family', $family);
            if ($user->current_family_id !== $family->id) {
                $user->forceFill(['current_family_id' => $family->id])->save();
            }
        }

        app()->instance(Family::class, $family);
        app()->instance('member', $member);
        View::share(['family' => $family, 'member' => $member]);

        return $next($request);
    }
}
