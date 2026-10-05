<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Story extends Model
{
    use RecordsActivity;

    protected $guarded = [];

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'person_story');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_story');
    }

    public function teller(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'told_by_person_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function audio(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'audio_media_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function excerpt(int $len = 220): string
    {
        return Str::limit(strip_tags($this->body), $len);
    }
}
