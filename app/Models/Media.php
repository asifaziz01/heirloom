<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Media extends Model
{
    use RecordsActivity;

    public const KINDS = ['photo' => 'Photo', 'video' => 'Video', 'audio' => 'Audio', 'document' => 'Document'];

    public const DOCUMENT_TYPES = ['certificate' => 'Certificate', 'letter' => 'Letter', 'record' => 'Record', 'passport' => 'Passport / ID', 'deed' => 'Deed / legal', 'newspaper' => 'Newspaper', 'other' => 'Other'];

    protected $guarded = [];

    protected $casts = ['ai_suggestions' => 'array'];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'media_person');
    }

    public static function kindFromMime(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'photo',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function url(): string
    {
        return route('media.file', $this);
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?: $this->original_name;
    }
}
