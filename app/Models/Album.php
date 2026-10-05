<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use RecordsActivity;

    protected $guarded = [];

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }
}
