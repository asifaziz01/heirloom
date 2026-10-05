<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    /** When true, model events do not write audit rows (used for bulk imports). */
    public static bool $muted = false;

    protected $guarded = [];

    protected $casts = ['changes' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function quietly(callable $callback): mixed
    {
        static::$muted = true;
        try {
            return $callback();
        } finally {
            static::$muted = false;
        }
    }
}
