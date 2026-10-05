<?php

namespace App\Http\Controllers;

use App\Models\Family;

class SourceController extends Controller
{
    /** Evidence register: every source with the facts it supports, plus facts still lacking a source. */
    public function index(Family $family)
    {
        return view('sources.index', [
            'sources' => $family->sources()->with(['facts.person', 'media'])->withCount('facts')->orderByDesc('facts_count')->get(),
            'unsourced' => $family->facts()->whereNull('source_id')->with('person')->latest()->get(),
            'disputed' => $family->facts()->where('status', 'disputed')->with(['person', 'source'])->get(),
        ]);
    }
}
