<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    protected $guarded = [];

    protected $casts = ['extraction' => 'array'];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /** True while an extraction job is queued or running. */
    public function isExtracting(): bool
    {
        return in_array($this->status, ['queued', 'extracting'], true);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function audio(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'audio_media_id');
    }
}
