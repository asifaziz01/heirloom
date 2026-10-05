<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Services\Governance;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

abstract class Controller
{
    protected function member(): FamilyMember
    {
        return app('member');
    }

    /**
     * Run a structural change through governance and redirect with the right message:
     * applied immediately for editors+, queued for review for contributors.
     *
     * @param  callable(mixed): string  $redirectTo  receives the result when applied
     */
    protected function governed(string $action, string $summary, array $payload, callable $redirectTo, string $doneMessage): RedirectResponse
    {
        try {
            $outcome = Governance::perform($this->member(), $action, $summary, $payload);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['relation' => $e->getMessage()]);
        }
        if (! $outcome['applied']) {
            return redirect($redirectTo(null))->with('status', 'Thanks! Your change was sent to the family admins for review.');
        }

        return redirect($redirectTo($outcome['result']))->with('status', $doneMessage);
    }
}
