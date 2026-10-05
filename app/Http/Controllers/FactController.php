<?php

namespace App\Http\Controllers;

use App\Models\Fact;
use App\Models\Family;
use App\Models\Person;
use App\Models\Source;
use App\Services\FamilyGraph;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FactController extends Controller
{
    public function store(Request $request, Family $family, Person $person)
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(array_keys(Fact::FIELDS))],
            'value' => 'required|string|max:255',
            'confidence' => ['required', Rule::in(array_keys(Fact::CONFIDENCE))],
            'status' => ['required', Rule::in(array_keys(Fact::STATUSES))],
            'source_id' => ['nullable', Rule::exists('sources', 'id')->where('family_id', $family->id)],
            'source_title' => 'nullable|string|max:200',
            'source_type' => ['nullable', Rule::in(array_keys(Source::TYPES))],
            'source_citation' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'is_preferred' => 'nullable|boolean',
        ]);
        // Only editors may mark a contributor's claim as verified.
        if (! $this->member()->atLeast('editor') && $data['status'] === 'verified') {
            $data['status'] = 'unverified';
        }

        return $this->governed(
            'fact.create',
            Fact::FIELDS[$data['field']]." for {$person->full_name}: {$data['value']}",
            ['person_id' => $person->id] + $data,
            fn () => route('people.show', [$family, $person]).'#facts',
            'Fact recorded with its source.',
        );
    }

    public function update(Request $request, Family $family, Fact $fact)
    {
        $data = $request->validate([
            'confidence' => ['required', Rule::in(array_keys(Fact::CONFIDENCE))],
            'status' => ['required', Rule::in(array_keys(Fact::STATUSES))],
        ]);
        $fact->update($data);

        return back()->with('status', 'Fact updated.');
    }

    public function prefer(Family $family, Fact $fact)
    {
        FamilyGraph::for($family)->preferFact($fact);

        return back()->with('status', 'This value is now shown on the profile.');
    }

    public function destroy(Family $family, Fact $fact)
    {
        $fact->delete();

        return back()->with('status', 'Fact removed.');
    }
}
