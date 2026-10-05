<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Support\Str;

/**
 * Writes an audit-log row for every create / update / delete made by a signed-in user.
 */
trait RecordsActivity
{
    protected static function bootRecordsActivity(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event) {
                if (ActivityLog::$muted || ! $model->family_id) {
                    return;
                }
                $changes = null;
                if ($event === 'updated') {
                    $dirty = collect($model->getChanges())->except(['updated_at']);
                    if ($dirty->isEmpty()) {
                        return;
                    }
                    $changes = $dirty->map(fn ($v, $k) => ['from' => $model->getOriginal($k), 'to' => $v])->all();
                }
                ActivityLog::create([
                    'family_id' => $model->family_id,
                    'user_id' => auth()->id(),
                    'action' => $event,
                    'subject_type' => class_basename($model),
                    'subject_id' => $model->getKey(),
                    'description' => Str::headline(class_basename($model)).' “'.$model->activityLabel().'”',
                    'changes' => $changes,
                ]);
            });
        }
    }

    public function activityLabel(): string
    {
        return (string) ($this->full_name ?? $this->title ?? $this->name ?? $this->original_name ?? '#'.$this->getKey());
    }
}
