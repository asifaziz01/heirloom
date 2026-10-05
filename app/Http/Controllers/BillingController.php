<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Plan selection. Payment collection (Stripe Checkout + webhooks) is not wired up yet;
 * in this build the owner switches plans directly so limits and features can be exercised.
 */
class BillingController extends Controller
{
    public function index(Family $family)
    {
        abort_unless(config('services.heritage.billing'), 404);

        return view('billing.index', [
            'plans' => config('plans'),
            'usage' => [
                'people' => $family->persons()->count(),
                'photos' => $family->media()->where('kind', 'photo')->count(),
                'storage_mb' => round($family->storage_used / 1048576, 1),
                'contributors' => $family->members()->whereIn('role', ['admin', 'editor', 'contributor'])->count(),
            ],
        ]);
    }

    public function change(Request $request, Family $family)
    {
        abort_unless(config('services.heritage.billing'), 404);
        abort_unless($this->member()->atLeast('owner'), 403, 'Only the family owner manages the subscription.');
        $data = $request->validate(['plan' => ['required', Rule::in(array_keys(config('plans')))]]);
        $family->update(['plan' => $data['plan']]);

        return back()->with('status', 'Plan changed to '.config("plans.{$data['plan']}.name").'.');
    }
}
