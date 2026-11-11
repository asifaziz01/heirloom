<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

    protected $casts = ['email_reminders' => 'boolean'];

    /** Secret for this member's personal calendar feed, created the first time it is needed. */
    public function calendarToken(): string
    {
        if (! $this->calendar_token) {
            $this->forceFill(['calendar_token' => Str::random(40)])->save();
        }

        return $this->calendar_token;
    }

    /**
     * A visitor browsing a public archive without an account (or a signed-in user who is
     * not a member). Never saved: it only carries the viewer role so that every
     * "$member->atLeast(...)" check in controllers and views answers correctly.
     */
    public static function guest(Family $family): self
    {
        $member = new self(['role' => 'viewer', 'family_id' => $family->id]);
        $member->setRelation('family', $family);

        return $member;
    }

    public function isGuest(): bool
    {
        return ! $this->exists;
    }

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

    /**
     * Editors and above can change anything; others only what they created themselves.
     * Guests never own anything, even records whose creator was deleted (created_by = null).
     */
    public function canManage(?int $createdBy): bool
    {
        return $this->atLeast('editor') || ($this->user_id !== null && $createdBy === $this->user_id);
    }

    /** Editors and above write directly; contributors' structural edits go to review. */
    public function needsReview(): bool
    {
        return $this->role === 'contributor';
    }
}
