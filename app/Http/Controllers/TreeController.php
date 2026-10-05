<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Person;
use App\Services\TreeBuilder;
use App\Support\PedigreeChart;
use Illuminate\Http\Request;

class TreeController extends Controller
{
    /** Descendant chart from a chosen root, grouping children under each partnership. */
    public function show(Request $request, Family $family)
    {
        $tree = new TreeBuilder($family);
        $roots = $tree->rootCandidates();
        $rootId = $request->integer('root') ?: ($roots[0]['person']->id ?? null);
        $depth = max(1, min(8, $request->integer('depth', 4)));

        return view('tree.show', [
            'tree' => $rootId ? $tree->descendants($rootId, $depth) : null,
            'roots' => $roots,
            'rootId' => $rootId,
            'depth' => $depth,
            'people' => collect($tree->people)->sortBy('given_name')->values(),
        ]);
    }

    public function pedigree(Request $request, Family $family, Person $person)
    {
        $generations = max(3, min(6, $request->integer('generations', 5)));
        $rows = (new TreeBuilder($family))->pedigree($person->id, $generations);

        return view('tree.pedigree', [
            'person' => $person,
            'generations' => $generations,
            'svg' => PedigreeChart::svg($rows, $family->name),
        ]);
    }

    /** Shareable "5 generation" chart as a standalone SVG file. */
    public function pedigreeDownload(Request $request, Family $family, Person $person)
    {
        $generations = max(3, min(6, $request->integer('generations', 5)));
        $svg = PedigreeChart::svg((new TreeBuilder($family))->pedigree($person->id, $generations), $family->name, standalone: true);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.str($person->full_name)->slug().'-'.$generations.'-generations.svg"',
        ]);
    }
}
