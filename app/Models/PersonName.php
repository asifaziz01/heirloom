<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonName extends Model
{
    public const TYPES = ['birth' => 'Birth name', 'married' => 'Married name', 'alternate' => 'Also known as', 'religious' => 'Religious name', 'nickname' => 'Nickname', 'transliteration' => 'In another script'];

    public const LANGUAGES = ['en' => 'English', 'hi' => 'Hindi', 'ur' => 'Urdu', 'ar' => 'Arabic', 'bn' => 'Bengali', 'pa' => 'Punjabi', 'gu' => 'Gujarati', 'ta' => 'Tamil', 'te' => 'Telugu', 'mr' => 'Marathi', 'fa' => 'Persian', 'fr' => 'French', 'es' => 'Spanish', 'de' => 'German', 'zh' => 'Chinese', 'other' => 'Other'];

    public const RTL = ['ur', 'ar', 'fa'];

    protected $guarded = [];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isRtl(): bool
    {
        return in_array($this->language, self::RTL, true);
    }
}
