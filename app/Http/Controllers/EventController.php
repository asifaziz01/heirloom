<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Family;
use App\Services\PlaceResolver;
use App\Support\PartialDate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request, Family $family)
    {
        $q = $family->events()->with(['people', 'place', 'fromPlace']);
        if ($type = $request->string('type')->value()) {
            $q->where('type', $type);
        }

        return view('events.index', ['events' => $q->get()->sortBy(fn ($e) => PartialDate::sortKey($e->date))->values()]);
    }

    public function create(Request $request, Family $family)
    {
        return view('events.form', [
            'event' => new Event(['type' => $request->string('type', 'custom')->value()]),
            'selected' => array_filter([$request->integer('person')]),
            'people' => $family->persons()->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
        ]);
    }

    public function store(Request $request, Family $family)
    {
        $data = $this->validated($request, $family);
        $event = $family->events()->create($this->attributes($family, $data) + ['created_by' => $request->user()->id]);
        $event->people()->sync($data['people'] ?? []);

        $returnTo = (string) $request->input('return_to');

        return redirect(str_starts_with($returnTo, url('/').'/') ? $returnTo : route('events.index', $family))
            ->with('status', 'Event added to the family timeline.');
    }

    public function edit(Family $family, Event $event)
    {
        return view('events.form', [
            'event' => $event->load(['place', 'fromPlace']),
            'selected' => $event->people()->pluck('persons.id')->all(),
            'people' => $family->persons()->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
        ]);
    }

    public function update(Request $request, Family $family, Event $event)
    {
        $data = $this->validated($request, $family);
        $event->update($this->attributes($family, $data));
        $event->people()->sync($data['people'] ?? []);

        return redirect()->route('events.index', $family)->with('status', 'Event updated.');
    }

    public function destroy(Family $family, Event $event)
    {
        abort_unless($this->member()->atLeast('editor') || $event->created_by === auth()->id(), 403);
        $event->delete();

        return redirect()->route('events.index', $family)->with('status', 'Event deleted.');
    }

    private function validated(Request $request, Family $family): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(Event::TYPES))],
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:10000',
            'date' => 'nullable|string|max:20',
            'date_qualifier' => 'nullable|in:exact,about,before,after',
            'end_date' => 'nullable|string|max:20',
            'place' => 'nullable|string|max:200',
            'from_place' => 'nullable|string|max:200',
            'people' => 'nullable|array',
            'people.*' => [Rule::exists('persons', 'id')->where('family_id', $family->id)],
        ]);
    }

    private function attributes(Family $family, array $data): array
    {
        return [
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'date' => PartialDate::normalize($data['date'] ?? null),
            'date_qualifier' => $data['date_qualifier'] ?? 'exact',
            'end_date' => PartialDate::normalize($data['end_date'] ?? null),
            'place_id' => PlaceResolver::resolve($family, $data['place'] ?? null),
            'from_place_id' => PlaceResolver::resolve($family, $data['from_place'] ?? null),
        ];
    }
}
