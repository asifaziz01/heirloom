<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMember extends Model
{
    public const ROLES = ['owner' => 5, 'admin' => 4, 'editor' => 3, 'contributor' => 2, 'viewer' => 1];

    public const ROLE_DESCRIPTIONS = [
        'owner' => 'Owns the family archive and subscription',
        'admin' => 'Manages members and settings, reviews contributions',
        'editor' => 'Edits the archive directly',
        'contributor' => 'Adds memories; changes to people and relationships are reviewed',
        'viewer' => 'Can browse the family archive',
    ];

    protected $guarded = [];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function atLeast(string $role): bool
    {
        return self::ROLES[$this->role] >= self::ROLES[$role];
    }

    /** Editors and above write directly; contributors' structural edits go to review. */
    public function needsReview(): bool
    {
        return $this->role === 'contributor';
    }
}
