<?php

namespace App\Http\Controllers;

use App\Models\AiTask;
use App\Models\Export;
use App\Models\Family;
use App\Models\Interview;
use App\Models\Media;
use Illuminate\Http\JsonResponse;

/**
 * Small members-only JSON endpoints that the pages poll while a queued job runs.
 * Every response is {"status": pending|running|done|failed|idle, "error": string|null, "url": string|null};
 * the page reloads (or follows "url") once the status is done or failed.
 */
class JobStatusController extends Controller
{
    public function interview(Family $family, Interview $interview): JsonResponse
    {
        $status = match ($interview->status) {
            'queued' => 'pending',
            'extracting' => 'running',
            'failed' => 'failed',
            'draft' => 'idle',
            default => 'done',
        };

        return $this->status($status, $status === 'failed' ? $interview->extraction_error : null);
    }

    public function media(Family $family, Media $media): JsonResponse
    {
        $status = $media->aiStatus() ?? 'idle';

        return $this->status($status, $status === 'failed' ? ($media->ai_suggestions['error'] ?? null) : null);
    }

    public function aiTask(Family $family, AiTask $aiTask): JsonResponse
    {
        $url = $aiTask->status === 'done' && $aiTask->type === 'biography' && $aiTask->person_id
            ? route('people.edit', [$family, $aiTask->person_id, 'draft' => $aiTask->id])
            : null;

        return $this->status($aiTask->status, $aiTask->error, $url);
    }

    public function export(Family $family, Export $export): JsonResponse
    {
        return $this->status($export->status, $export->error);
    }

    private function status(string $status, ?string $error = null, ?string $url = null): JsonResponse
    {
        return response()->json(['status' => $status, 'error' => $error, 'url' => $url])
            ->header('Cache-Control', 'no-store');
    }
}
