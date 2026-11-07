<?php

namespace App\Jobs;

use App\Models\AiTask;
use App\Services\HeritageAi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Person-level AI suggestions: a biography draft or interview questions. The result is stored
 * on the task; the user copies it into the profile (or posts the questions) themselves.
 */
class RunAiTask implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 540;

    public function __construct(public AiTask $task) {}

    public function handle(HeritageAi $ai): void
    {
        $task = $this->task->fresh('person');
        if (! $task) {
            return;
        }
        if (! $task->person) {
            $task->update(['status' => 'failed', 'error' => 'This person is no longer in the archive.']);

            return;
        }
        $task->update(['status' => 'running', 'error' => null]);

        try {
            $result = match ($task->type) {
                'biography' => ['biography' => $ai->biography($task->person)],
                'questions' => ['questions' => $ai->suggestQuestions($task->person)],
                default => throw new RuntimeException('Unknown AI task.'),
            };
        } catch (RuntimeException $e) {
            $task->update(['status' => 'failed', 'error' => $e->getMessage()]);

            return;
        }

        $task->update(['status' => 'done', 'result' => $result, 'error' => null]);
    }

    public function failed(?Throwable $e): void
    {
        AiTask::whereKey($this->task->id)->update(['status' => 'failed', 'error' => 'The AI request stopped unexpectedly. Please try again.']);
    }
}
