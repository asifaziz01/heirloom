<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    use RecordsActivity;

    protected $guarded = [];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float'];

    public function getLabelAttribute(): string
    {
        $country = $this->country && ! str_contains($this->name, $this->country) ? $this->country : null;

        return collect([$this->name, $country])->filter()->implode(', ');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
