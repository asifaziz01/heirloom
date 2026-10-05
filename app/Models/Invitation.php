<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $guarded = [];

    protected $casts = ['accepted_at' => 'datetime', 'expires_at' => 'datetime'];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isUsable(): bool
    {
        return ! $this->accepted_at && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
