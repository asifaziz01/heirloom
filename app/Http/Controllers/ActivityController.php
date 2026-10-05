<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request, Family $family)
    {
        $q = $family->activity()->with('user')->latest();
        if ($type = $request->string('type')->value()) {
            $q->where('subject_type', $type);
        }

        return view('activity.index', [
            'logs' => $q->paginate(50)->withQueryString(),
            'types' => $family->activity()->distinct()->pluck('subject_type')->filter(),
        ]);
    }
}
