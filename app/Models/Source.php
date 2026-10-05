<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    use RecordsActivity;

    public const TYPES = ['document' => 'Document', 'recollection' => 'Family recollection', 'interview' => 'Recorded interview', 'record' => 'Official record', 'book' => 'Book / publication', 'website' => 'Website', 'gravestone' => 'Gravestone'];

    protected $guarded = [];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function facts(): HasMany
    {
        return $this->hasMany(Fact::class);
    }
}
