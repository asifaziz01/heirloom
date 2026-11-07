<?php

namespace App\Jobs;

use App\Models\Export;
use App\Services\ArchiveExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Builds the complete family archive ZIP on the private local disk under families/{id}/exports/.
 * The export record tracks progress; the file is served by ExportController::download.
 */
class BuildArchive implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 540;

    public function __construct(public Export $export) {}

    public function handle(): void
    {
        $export = $this->export->fresh('family');
        if (! $export || ! $export->family) {
            return;
        }
        $export->update(['status' => 'running', 'error' => null]);
        Export::pruneStale();

        $disk = Storage::disk('local');
        $dir = "families/{$export->family_id}/exports";
        $disk->makeDirectory($dir);
        $relative = $dir.'/'.$export->family->slug.'-archive-'.now()->format('Y-m-d-His').'.zip';

        try {
            (new ArchiveExporter($export->family))->build($disk->path($relative));
        } catch (RuntimeException $e) {
            $disk->delete($relative);
            $export->update(['status' => 'failed', 'error' => $e->getMessage()]);

            return;
        }

        $export->update([
            'status' => 'done',
            'path' => $relative,
            'filename' => basename($relative),
            'size' => $disk->size($relative),
            'error' => null,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        Export::whereKey($this->export->id)->update(['status' => 'failed', 'error' => 'The archive could not be built. Please try again.']);
    }
}
