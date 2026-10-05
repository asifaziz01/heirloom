<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $guarded = [];

    public function about(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'about_person_id');
    }

    public function asker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asked_by');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'answer_story_id');
    }
}
