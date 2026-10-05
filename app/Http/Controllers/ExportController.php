<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Services\ArchiveExporter;
use App\Services\Gedcom\GedcomExporter;
use App\Services\Gedcom\GedcomImporter;
use App\Services\Timeline;
use App\Services\TreeBuilder;
use App\Support\PartialDate;
use App\Support\PedigreeChart;
use Illuminate\Http\Request;
use RuntimeException;

class ExportController extends Controller
{
    public function index(Family $family)
    {
        return view('export.index', [
            'counts' => [
                'people' => $family->persons()->count(),
                'files' => $family->media()->count(),
                'stories' => $family->stories()->count(),
                'mb' => round($family->storage_used / 1048576, 1),
            ],
        ]);
    }

    public function gedcom(Family $family)
    {
        abort_unless($family->hasFeature('gedcom'), 403, 'GEDCOM export is part of the Heritage plan.');

        return response((new GedcomExporter($family))->export(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$family->slug.'.ged"',
        ]);
    }

    public function importGedcom(Request $request, Family $family)
    {
        $request->validate(['gedcom' => 'required|file|max:51200']);
        try {
            $stats = (new GedcomImporter($family, $request->user()->id))->import($request->file('gedcom')->get());
        } catch (RuntimeException $e) {
            return back()->withErrors(['gedcom' => $e->getMessage()]);
        }
        $warnings = count($stats['warnings'] ?? []);

        return redirect()->route('people.index', $family)->with('status', "Imported {$stats['people']} people, {$stats['partnerships']} partnerships and {$stats['links']} parent–child links."
            .($warnings ? " {$warnings} lines could not be read." : ''));
    }

    public function archive(Family $family)
    {
        abort_unless($family->hasFeature('archive_export'), 403, 'Full archive export is part of the Heritage plan.');

        return response()->download((new ArchiveExporter($family))->build())->deleteFileAfterSend();
    }

    /** Printable family book: open in the browser and "Save as PDF". */
    public function book(Request $request, Family $family)
    {
        abort_unless($family->hasFeature('book'), 403, 'The family book is part of the Legacy plan.');
        $tree = new TreeBuilder($family);
        $gens = $tree->generations();
        $people = $family->persons()->visibleTo($this->member())
            ->with(['birthPlace', 'deathPlace', 'photo', 'names', 'stories'])->get()
            ->sortBy(fn ($p) => PartialDate::sortKey($p->birth_date));
        $chapters = $people->groupBy(fn ($p) => $gens[$p->id] ?? 1)->sortKeys();
        $focus = $this->member()->person ?? $people->first();
        $years = $people->map(fn ($p) => PartialDate::year($p->birth_date))->filter();

        return view('book.show', [
            'chapters' => $chapters,
            'chapterTitles' => [1 => 'Our Origins', 2 => 'The Elders', 3 => 'Growing Family', 4 => 'Branching Out', 5 => 'The Current Generation', 6 => 'The Newest Generation'],
            'stories' => $family->stories()->with('people')->get(),
            'timeline' => Timeline::forFamily($family)->whereIn('type', ['birth', 'death', 'marriage', 'migration'])->values(),
            'pedigree' => $focus ? PedigreeChart::svg($tree->pedigree($focus->id, 4), $family->name) : null,
            'span' => $years->isNotEmpty() ? $years->min().'–'.now()->year : now()->year,
            'stats' => [
                'people' => $people->count(),
                'generations' => $chapters->count(),
                'places' => $family->places()->count(),
                'countries' => $family->places()->whereNotNull('country')->distinct()->count('country'),
                'stories' => $family->stories()->count(),
                'photos' => $family->media()->where('kind', 'photo')->count(),
            ],
            'tree' => $tree,
        ]);
    }
}
