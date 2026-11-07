<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One request for a complete ZIP archive. The job writes the file to the private local disk
 * under families/{id}/exports/; it is removed after download or after a day.
 */
class Export extends Model
{
    protected $guarded = [];

    protected $casts = ['downloaded_at' => 'datetime'];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isWorking(): bool
    {
        return in_array($this->status, ['pending', 'running'], true);
    }

    public function isReady(): bool
    {
        return $this->status === 'done' && $this->path && Storage::disk('local')->exists($this->path);
    }

    /** Deletes the ZIP (if any) and the record. */
    public function purge(): void
    {
        if ($this->path) {
            Storage::disk('local')->delete($this->path);
        }
        $this->delete();
    }

    /** Removes downloaded archives and anything older than a day, so exports never pile up on disk. */
    public static function pruneStale(): void
    {
        static::where(fn ($q) => $q->whereNotNull('downloaded_at')->orWhere('created_at', '<', now()->subDay()))
            ->get()->each->purge();
    }
}
