<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A queued AI request about one person (a biography draft or interview-question ideas).
 * The result is a suggestion the user reviews; the job never writes to the archive itself.
 */
class AiTask extends Model
{
    public const TYPES = ['biography', 'questions'];

    protected $guarded = [];

    protected $casts = ['result' => 'array'];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isWorking(): bool
    {
        return in_array($this->status, ['pending', 'running'], true);
    }
}
