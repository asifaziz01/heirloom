<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\ParentChild;
use App\Models\Partnership;
use App\Models\Person;
use App\Services\RelationshipCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RelationshipController extends Controller
{
    /** Connect two people who are already in the archive. */
    public function link(Request $request, Family $family, Person $person)
    {
        $data = $request->validate([
            'relation' => 'required|in:parent,child,partner,sibling',
            'other_id' => ['required', 'integer', Rule::notIn([$person->id]), Rule::exists('persons', 'id')->where('family_id', $family->id)],
            'link_type' => ['nullable', Rule::in(array_keys(ParentChild::TYPES))],
            'partnership_id' => ['nullable', Rule::exists('partnerships', 'id')->where('family_id', $family->id)],
            'coparent_id' => ['nullable', Rule::exists('persons', 'id')->where('family_id', $family->id)],
            'partnership_type' => ['nullable', Rule::in(array_keys(Partnership::TYPES))],
            'status' => ['nullable', Rule::in(array_keys(Partnership::STATUSES))],
            'start_date' => 'nullable|string|max:20',
            'end_date' => 'nullable|string|max:20',
        ]);
        $other = $family->persons()->findOrFail($data['other_id']);
        $type = $data['link_type'] ?? 'biological';

        [$action, $payload, $summary] = match ($data['relation']) {
            'parent' => ['parent.link', ['parent_id' => $other->id, 'child_id' => $person->id, 'type' => $type, 'coparent_id' => $data['coparent_id'] ?? null],
                "{$other->full_name} is a {$type} parent of {$person->full_name}"],
            'child' => ['child.link', ['parent_id' => $person->id, 'child_id' => $other->id, 'type' => $type, 'partnership_id' => $data['partnership_id'] ?? null],
                "{$other->full_name} is a {$type} child of {$person->full_name}"],
            'partner' => ['partnership.create', ['partner1_id' => $person->id, 'partner2_id' => $other->id] + $data,
                "{$person->full_name} and {$other->full_name} were partners"],
            'sibling' => ['sibling.link', ['person_id' => $person->id, 'sibling_id' => $other->id],
                "{$other->full_name} is a sibling of {$person->full_name}"],
        };

        return $this->governed($action, $summary, $payload, fn () => route('people.show', [$family, $person]), 'Relationship saved.');
    }

    public function unlink(Family $family, ParentChild $link)
    {
        abort_unless($link->family_id === $family->id, 404);
        $link->load(['parent', 'child']);

        return $this->governed(
            'parent.unlink',
            "Remove link: {$link->parent?->full_name} → {$link->child?->full_name}",
            ['id' => $link->id],
            fn () => url()->previous(),
            'Link removed.',
        );
    }

    public function editPartnership(Family $family, Partnership $partnership)
    {
        $partnership->load(['partner1', 'partner2', 'place']);

        return view('partnerships.edit', ['partnership' => $partnership]);
    }

    public function updatePartnership(Request $request, Family $family, Partnership $partnership)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Partnership::TYPES))],
            'status' => ['required', Rule::in(array_keys(Partnership::STATUSES))],
            'start_date' => 'nullable|string|max:20',
            'end_date' => 'nullable|string|max:20',
            'place' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:5000',
        ]);
        $partnership->load(['partner1', 'partner2']);

        return $this->governed(
            'partnership.update',
            'Edit partnership of '.$partnership->partner1->full_name.' & '.($partnership->partner2?->full_name ?? 'unknown'),
            ['id' => $partnership->id] + $data,
            fn () => route('people.show', [$family, $partnership->partner1_id]),
            'Partnership updated.',
        );
    }

    public function destroyPartnership(Family $family, Partnership $partnership)
    {
        $partnership->load(['partner1', 'partner2']);

        return $this->governed(
            'partnership.delete',
            'Remove partnership of '.$partnership->partner1->full_name.' & '.($partnership->partner2?->full_name ?? 'unknown'),
            ['id' => $partnership->id],
            fn () => route('people.show', [$family, $partnership->partner1_id]),
            'Partnership removed. Children keep their parent links.',
        );
    }

    public function calculator(Request $request, Family $family)
    {
        $people = $family->persons()->visibleTo($this->member())->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']);
        $result = null;
        $a = $request->integer('a') ?: $this->member()->person_id;
        $b = $request->integer('b');
        if ($a && $b && $people->contains('id', $a) && $people->contains('id', $b)) {
            $result = (new RelationshipCalculator($family))->describe($a, $b);
        }

        return view('relationship.calculator', compact('people', 'result', 'a', 'b'));
    }
}
