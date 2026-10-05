<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Place;

class PlaceResolver
{
    /** Find a place by (case-insensitive) name within the family, creating it when new. */
    public static function resolve(Family|int $family, ?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $familyId = $family instanceof Family ? $family->id : $family;
        $existing = Place::where('family_id', $familyId)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            return $existing->id;
        }
        $parts = array_map('trim', explode(',', $name));

        return Place::create([
            'family_id' => $familyId,
            'name' => $name,
            'city' => $parts[0] ?? null,
            'country' => count($parts) > 1 ? end($parts) : null,
        ])->id;
    }
}
