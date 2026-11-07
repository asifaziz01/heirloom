<?php

namespace App\Jobs;

use App\Models\Interview;
use App\Services\HeritageAi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Runs the AI extraction for an interview transcript. The result is stored as suggestions
 * on the interview; the family confirms each item before anything enters the archive.
 *
 * Interview status: draft -> queued -> extracting -> extracted | failed -> applied.
 */
class ExtractInterview implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 540;

    public function __construct(public Interview $interview) {}

    public function handle(HeritageAi $ai): void
    {
        $interview = $this->interview->fresh(['person', 'family']);
        if (! $interview) {
            return;
        }
        $interview->update(['status' => 'extracting', 'extraction_error' => null]);

        $known = $interview->family->persons()->get()
            ->map(fn ($p) => "{$p->id}: {$p->full_name}".($p->lifespan ? " ({$p->lifespan})" : ''))->all();
        try {
            $extraction = $ai->extractInterview((string) $interview->transcript, $interview->person, $known);
        } catch (RuntimeException $e) {
            // HeritageAi turns API problems into RuntimeException: record it for the user, do not retry.
            $interview->update(['status' => 'failed', 'extraction_error' => $e->getMessage()]);

            return;
        }

        $interview->update(['extraction' => $extraction, 'status' => 'extracted', 'extraction_error' => null]);
    }

    public function failed(?Throwable $e): void
    {
        Interview::whereKey($this->interview->id)->update([
            'status' => 'failed',
            'extraction_error' => 'Extraction stopped unexpectedly. Please try again.',
        ]);
    }
}
