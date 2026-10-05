<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Family;
use App\Models\Place;
use App\Support\PartialDate;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    /** Places map with migration routes (from "migration" events and birth → death places). */
    public function index(Family $family)
    {
        $places = $family->places()->orderBy('name')->get();
        $counts = [];
        foreach ($family->persons()->get(['birth_place_id', 'death_place_id']) as $p) {
            foreach ([$p->birth_place_id, $p->death_place_id] as $id) {
                if ($id) {
                    $counts[$id] = ($counts[$id] ?? 0) + 1;
                }
            }
        }
        $migrations = Event::where('family_id', $family->id)->where('type', 'migration')
            ->whereNotNull('place_id')->whereNotNull('from_place_id')
            ->with(['place', 'fromPlace', 'people'])->get()
            ->sortBy(fn ($e) => PartialDate::sortKey($e->date))->values();

        return view('places.index', [
            'places' => $places,
            'counts' => $counts,
            'migrations' => $migrations,
            'mapData' => [
                'places' => $places->filter->hasCoordinates()->map(fn ($p) => [
                    'name' => $p->label, 'lat' => $p->latitude, 'lng' => $p->longitude, 'count' => $counts[$p->id] ?? 0,
                ])->values(),
                'routes' => $migrations->filter(fn ($e) => $e->place->hasCoordinates() && $e->fromPlace->hasCoordinates())
                    ->map(fn ($e) => [
                        'from' => [$e->fromPlace->latitude, $e->fromPlace->longitude],
                        'to' => [$e->place->latitude, $e->place->longitude],
                        'label' => ($e->date ? PartialDate::year($e->date).' · ' : '').$e->title,
                    ])->values(),
            ],
            'countries' => $places->pluck('country')->filter()->countBy()->sortDesc(),
        ]);
    }

    public function update(Request $request, Family $family, Place $place)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $place->update($data);

        return back()->with('status', "{$place->name} updated.");
    }
}
