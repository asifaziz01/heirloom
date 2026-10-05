<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\ParentChild;
use App\Models\Partnership;
use App\Models\Person;
use App\Models\PersonName;
use App\Services\Timeline;
use App\Support\PartialDate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonController extends Controller
{
    public function index(Request $request, Family $family)
    {
        $q = $family->persons()->visibleTo($this->member())->with(['birthPlace', 'photo']);
        if ($term = $request->string('q')->trim()->value()) {
            $q->search($term);
        }
        if ($surname = $request->string('surname')->value()) {
            $q->where('surname', $surname);
        }
        match ($request->string('filter')->value()) {
            'living' => $q->where('is_living', true),
            'deceased' => $q->where('is_living', false),
            'no-photo' => $q->whereNull('photo_media_id'),
            'no-birth' => $q->whereNull('birth_date'),
            default => null,
        };
        $sort = $request->string('sort', 'name')->value();
        $people = $sort === 'born'
            ? $q->get()->sortBy(fn ($p) => PartialDate::sortKey($p->birth_date))->values()
            : $q->orderBy('surname')->orderBy('given_name')->get();

        return view('people.index', [
            'people' => $people,
            'surnames' => $family->persons()->whereNotNull('surname')->selectRaw('surname, count(*) as n')->groupBy('surname')->orderByDesc('n')->limit(20)->pluck('n', 'surname'),
        ]);
    }

    public function create(Request $request, Family $family)
    {
        $of = $request->integer('of') ? $family->persons()->find($request->integer('of')) : null;

        return view('people.create', [
            'relation' => $of ? $request->string('relation', 'child')->value() : null,
            'of' => $of,
            'partnerships' => $of ? $of->partnerships() : collect(),
            'ofParents' => $of ? $of->parents() : collect(),
            'people' => $family->persons()->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
        ]);
    }

    public function store(Request $request, Family $family)
    {
        if ($msg = $family->limitReached('people')) {
            return back()->withInput()->withErrors(['given_name' => $msg]);
        }
        $data = $this->validatePerson($request);
        $relation = null;
        if ($request->filled('relation') && $request->filled('of')) {
            $relation = $request->validate([
                'relation' => 'in:parent,child,partner,sibling',
                'of' => ['integer', Rule::exists('persons', 'id')->where('family_id', $family->id)],
                'link_type' => ['nullable', Rule::in(array_keys(ParentChild::TYPES))],
                'partnership_id' => ['nullable', Rule::exists('partnerships', 'id')->where('family_id', $family->id)],
                'coparent_id' => ['nullable', Rule::exists('persons', 'id')->where('family_id', $family->id)],
                'partnership_type' => ['nullable', Rule::in(array_keys(Partnership::TYPES))],
                'status' => ['nullable', Rule::in(array_keys(Partnership::STATUSES))],
                'start_date' => 'nullable|string|max:20',
                'end_date' => 'nullable|string|max:20',
            ]);
            $relation = ['type' => $relation['relation'], 'person_id' => (int) $relation['of']] + $relation;
        }
        $name = trim($data['given_name'].' '.($data['surname'] ?? ''));

        return $this->governed(
            'person.create',
            $relation ? "Add {$name} as {$relation['type']} of ".$family->persons()->find($relation['person_id'])->full_name : "Add {$name}",
            ['person' => $data, 'relation' => $relation],
            fn ($person) => $person ? route('people.show', [$family, $person]) : ($relation ? route('people.show', [$family, $relation['person_id']]) : route('people.index', $family)),
            "{$name} was added to the family.",
        );
    }

    public function show(Family $family, Person $person)
    {
        abort_unless(Person::whereKey($person->id)->visibleTo($this->member())->exists(), 403, 'This profile is private.');
        $person->load(['names', 'birthPlace', 'deathPlace', 'photo', 'facts.source', 'facts.addedBy', 'stories', 'events.place']);
        $partnerships = $person->partnerships();
        $children = $person->children();
        $groupedChildIds = [];
        $unions = $partnerships->map(function (Partnership $u) use ($person, $children, &$groupedChildIds) {
            $kids = $children->filter(fn ($c) => $c->link->partnership_id === $u->id)->values();
            $groupedChildIds = array_merge($groupedChildIds, $kids->pluck('id')->all());

            return ['partnership' => $u, 'partner' => $u->partnerOf($person), 'children' => $kids];
        });

        return view('people.show', [
            'person' => $person,
            'parents' => $person->parents(),
            'siblings' => $person->siblings(),
            'unions' => $unions,
            'otherChildren' => $children->reject(fn ($c) => in_array($c->id, $groupedChildIds, true))->values(),
            'media' => $person->media()->latest()->take(12)->get(),
            'timeline' => Timeline::forPerson($person)->take(12),
            'completeness' => $person->completeness(),
            'linkedMember' => $family->members()->where('person_id', $person->id)->with('user')->first(),
            'people' => $family->persons()->where('id', '!=', $person->id)->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
            'factsByField' => $person->facts->groupBy('field'),
        ]);
    }

    public function timeline(Family $family, Person $person)
    {
        return view('people.timeline', ['person' => $person, 'items' => Timeline::forPerson($person)]);
    }

    public function edit(Family $family, Person $person)
    {
        $person->load(['birthPlace', 'deathPlace', 'names']);

        return view('people.edit', ['person' => $person, 'photos' => $person->media()->where('kind', 'photo')->get()]);
    }

    public function update(Request $request, Family $family, Person $person)
    {
        $data = $this->validatePerson($request);

        return $this->governed(
            'person.update',
            "Edit {$person->full_name}",
            ['id' => $person->id, 'person' => $data],
            fn () => route('people.show', [$family, $person]),
            'Profile updated.',
        );
    }

    public function destroy(Family $family, Person $person)
    {
        return $this->governed(
            'person.delete',
            "Remove {$person->full_name}",
            ['id' => $person->id],
            fn () => route('people.index', $family),
            "{$person->full_name} was removed.",
        );
    }

    public function storeName(Request $request, Family $family, Person $person)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:200',
            'language' => ['required', Rule::in(array_keys(PersonName::LANGUAGES))],
            'type' => ['required', Rule::in(array_keys(PersonName::TYPES))],
        ]);
        $person->names()->create($data);

        return back()->with('status', 'Name added.');
    }

    public function destroyName(Family $family, Person $person, PersonName $name)
    {
        abort_unless($name->person_id === $person->id && $person->family_id === $family->id, 404);
        $name->delete();

        return back()->with('status', 'Name removed.');
    }

    public function setPhoto(Request $request, Family $family, Person $person)
    {
        $data = $request->validate(['media_id' => ['required', Rule::exists('media', 'id')->where('family_id', $family->id)->where('kind', 'photo')]]);

        return $this->governed(
            'person.update',
            "Set profile photo for {$person->full_name}",
            ['id' => $person->id, 'person' => ['photo_media_id' => (int) $data['media_id']]],
            fn () => route('people.show', [$family, $person]),
            'Profile photo updated.',
        );
    }

    private function validatePerson(Request $request): array
    {
        $data = $request->validate([
            'given_name' => 'required|string|max:100',
            'surname' => 'nullable|string|max:100',
            'nickname' => 'nullable|string|max:100',
            'gender' => 'required|in:male,female,other,unknown',
            'birth_date' => 'nullable|string|max:20',
            'birth_qualifier' => 'nullable|in:exact,about,before,after',
            'birth_place' => 'nullable|string|max:200',
            'is_living' => 'nullable|boolean',
            'death_date' => 'nullable|string|max:20',
            'death_qualifier' => 'nullable|in:exact,about,before,after',
            'death_place' => 'nullable|string|max:200',
            'occupation' => 'nullable|string|max:200',
            'education' => 'nullable|string|max:200',
            'religion' => 'nullable|string|max:100',
            'biography' => 'nullable|string|max:50000',
            'privacy' => 'nullable|in:family,admins',
            'branch' => 'nullable|string|max:100',
            'alt_name' => 'nullable|string|max:200',
            'alt_language' => 'nullable|string|max:8',
            'alt_type' => 'nullable|string|max:20',
        ]);
        foreach (['birth_date', 'death_date'] as $f) {
            if (! empty($data[$f]) && ! PartialDate::normalize($data[$f])) {
                abort(back()->withInput()->withErrors([$f => 'Use a date like 1942, 1942-03, 1942-03-15 or 15 March 1942.']));
            }
        }
        $data['is_living'] = $request->boolean('is_living') && empty($data['death_date']);
        $data['birth_qualifier'] ??= 'exact';
        $data['death_qualifier'] ??= 'exact';
        $data['privacy'] ??= 'family';
        if (! empty($data['alt_name'])) {
            $data['names'] = [['full_name' => $data['alt_name'], 'language' => $data['alt_language'] ?? 'en', 'type' => $data['alt_type'] ?? 'transliteration']];
        }
        unset($data['alt_name'], $data['alt_language'], $data['alt_type']);

        return $data;
    }
}
