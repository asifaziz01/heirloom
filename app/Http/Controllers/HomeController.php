<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return view('landing');
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
