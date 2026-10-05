<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Person;
use App\Services\HeritageAi;
use RuntimeException;

class AiController extends Controller
{
    /** Draft a biography; the user edits it on the profile form before anything is saved. */
    public function biography(Family $family, Person $person, HeritageAi $ai)
    {
        abort_unless($family->hasFeature('ai'), 403, 'AI biographies are part of the Heritage plan.');
        try {
            $draft = $ai->biography($person);
        } catch (RuntimeException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return redirect()->route('people.edit', [$family, $person])
            ->withInput(['biography' => $draft] + $person->only(['given_name', 'surname', 'nickname', 'gender', 'occupation', 'education', 'religion', 'branch']))
            ->with('status', 'Here is an AI draft biography. Check every detail, edit it, then save.');
    }

    public function questions(Family $family, Person $person, HeritageAi $ai)
    {
        abort_unless($family->hasFeature('ai'), 403, 'AI features are part of the Heritage plan.');
        try {
            $questions = $ai->suggestQuestions($person);
        } catch (RuntimeException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('suggested_questions', $questions);
    }
}
