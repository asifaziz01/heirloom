<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Media;
use App\Services\HeritageAi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * AI photo/document analysis. Progress and the result both live in media.ai_suggestions:
 * {"status": pending|running|done|failed, "error": ...} plus the suggestion fields once done.
 */
class AnalyzeMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 540;

    public function __construct(public Media $media) {}

    public function handle(HeritageAi $ai): void
    {
        $media = $this->media->fresh('family');
        if (! $media) {
            return;
        }
        self::setSuggestions($media, ['status' => 'running']);

        $known = $media->family->persons()->get()
            ->map(fn ($p) => "{$p->id}: {$p->full_name}".($p->lifespan ? " ({$p->lifespan})" : ''))->all();
        try {
            $result = $ai->analyzeMedia($media, $known);
        } catch (RuntimeException $e) {
            self::setSuggestions($media, ['status' => 'failed', 'error' => $e->getMessage()]);

            return;
        }

        self::setSuggestions($media, ['status' => 'done'] + $result);
    }

    public function failed(?Throwable $e): void
    {
        if ($media = Media::find($this->media->id)) {
            self::setSuggestions($media, ['status' => 'failed', 'error' => 'Analysis stopped unexpectedly. Please try again.']);
        }
    }

    /** Suggestions are not archive content, so their bookkeeping stays out of the audit log. */
    public static function setSuggestions(Media $media, array $suggestions): void
    {
        ActivityLog::quietly(fn () => $media->update(['ai_suggestions' => $suggestions]));
    }
}
