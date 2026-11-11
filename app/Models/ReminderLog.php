<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/** Remembers which reminder batches have gone out, so none is ever sent twice. */
class ReminderLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'reminder_log';

    protected $guarded = [];

    /** True the first time a key is claimed; false if it was already sent. Safe under concurrent runs. */
    public static function claim(int $familyId, string $key): bool
    {
        try {
            static::create(['family_id' => $familyId, 'key' => $key, 'created_at' => now()]);

            return true;
        } catch (QueryException $e) {
            return false; // unique key: someone (or an earlier run) got there first
        }
    }
}
