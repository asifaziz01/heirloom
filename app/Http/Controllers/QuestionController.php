<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public const STARTERS = [
        'Where did you grow up, and what was the house like?',
        'How did your parents meet?',
        'What did your grandparents do for a living?',
        'What festivals or traditions did the family keep, and how?',
        'Why did the family move, and what was the journey like?',
        'What was your first job?',
        'Which family recipe should never be lost?',
        'What do you remember about your wedding day?',
        'Who in the family was the best storyteller, and what stories did they tell?',
        'What were the hardest years for the family, and how did you get through them?',
    ];

    public function index(Family $family)
    {
        return view('questions.index', [
            'open' => $family->questions()->where('status', 'open')->with(['about', 'asker'])->latest()->get(),
            'answered' => $family->questions()->where('status', 'answered')->with(['about', 'answer'])->latest()->take(30)->get(),
            'people' => $family->persons()->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
            'starters' => self::STARTERS,
        ]);
    }

    public function store(Request $request, Family $family)
    {
        $data = $request->validate([
            'question' => 'required|string|max:1000',
            'about_person_id' => ['nullable', Rule::exists('persons', 'id')->where('family_id', $family->id)],
        ]);
        $family->questions()->create($data + ['asked_by' => $request->user()->id]);

        return back()->with('status', 'Question added. Anyone in the family can answer it with a story.');
    }

    public function answer(Family $family, Question $question)
    {
        return redirect()->route('stories.create', [$family, 'question' => $question->id]);
    }

    public function destroy(Family $family, Question $question)
    {
        abort_unless($this->member()->atLeast('editor') || $question->asked_by === auth()->id(), 403);
        $question->delete();

        return back()->with('status', 'Question removed.');
    }
}
