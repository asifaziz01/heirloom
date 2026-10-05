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

        return $family ? redirect()->route('families.show', $family) : redirect()->route('families.create');
    }
}
