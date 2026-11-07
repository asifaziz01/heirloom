<?php

namespace App\Http\Controllers;

use App\Jobs\RunAiTask;
use App\Models\AiTask;
use App\Models\Family;
use App\Models\Person;
use Illuminate\Http\Request;

/**
 * Person-level AI suggestions. Each request becomes an AiTask that runs on the queue; the profile
 * page polls it and shows the result, and the user still reviews everything before saving.
 */
class AiController extends Controller
{
    /** Draft a biography; the user edits it on the profile form before anything is saved. */
    public function biography(Request $request, Family $family, Person $person)
    {
        abort_unless($family->hasFeature('ai'), 403, 'AI biographies are part of the Heritage plan.');
        $task = $this->start($request, $family, $person, 'biography');

        // With QUEUE_CONNECTION=sync the draft is ready now: open it in the editor straight away.
        if ($task->status === 'done') {
            return redirect()->route('people.edit', [$family, $person, 'draft' => $task->id]);
        }

        return redirect()->route('people.show', [$family, $person])
            ->with('status', $task->status === 'failed' ? 'The draft could not be written; see the message on the profile.' : 'Drafting a biography… this usually takes a minute. It will appear on the profile when it is ready.');
    }

    public function questions(Request $request, Family $family, Person $person)
    {
        abort_unless($family->hasFeature('ai'), 403, 'AI features are part of the Heritage plan.');
        $task = $this->start($request, $family, $person, 'questions');

        return redirect()->route('people.show', [$family, $person, '#ask'])
            ->with('status', $task->status === 'done' ? 'Suggested questions are ready below.' : 'Thinking of questions… they will appear below when ready.');
    }

    private function start(Request $request, Family $family, Person $person, string $type): AiTask
    {
        $running = $family->aiTasks()->where('person_id', $person->id)->where('type', $type)->whereIn('status', ['pending', 'running'])->first();
        if ($running) {
            return $running;
        }
        $task = $family->aiTasks()->create(['person_id' => $person->id, 'user_id' => $request->user()->id, 'type' => $type, 'status' => 'pending']);
        RunAiTask::dispatch($task);

        return $task->fresh();
    }
}
