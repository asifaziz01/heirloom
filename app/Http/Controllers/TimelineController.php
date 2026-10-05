<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Services\Timeline;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function index(Request $request, Family $family)
    {
        $items = Timeline::forFamily($family);
        if ($types = array_filter((array) $request->input('types'))) {
            $items = $items->whereIn('type', $types)->values();
        }
        if ($from = $request->integer('from')) {
            $items = $items->filter(fn ($i) => $i['year'] >= $from)->values();
        }
        if ($to = $request->integer('to')) {
            $items = $items->filter(fn ($i) => $i['year'] <= $to)->values();
        }

        return view('timeline.index', [
            'byDecade' => $items->groupBy(fn ($i) => intdiv($i['year'], 10) * 10),
            'count' => $items->count(),
        ]);
    }
}
