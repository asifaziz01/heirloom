<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            // A single public archive is the site, as far as a visitor is concerned: take them straight in.
            $public = Family::where('is_public', true)->withCount('persons')->orderBy('name')->get();
            if ($public->count() === 1) {
                return redirect()->route('families.show', $public->first());
            }

            return view('landing', ['publicFamilies' => $public]);
        }
        $family = $user->currentFamily ?? $user->families()->first();
        if (! $family) {
            return redirect()->route('families.create');
        }

        // App shortcuts from the home-screen icon (see public/manifest.webmanifest)
        $shortcut = match ($request->query('go')) {
            'upload' => 'media.create',
            'interview' => 'interviews.create',
            'story' => 'stories.create',
            default => 'families.show',
        };

        return redirect()->route($shortcut, $family);
    }
}
