<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'current_family_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function families(): BelongsToMany
    {
        return $this->belongsToMany(Family::class, 'family_members')->withPivot('role', 'person_id')->withTimestamps();
    }

    public function currentFamily(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'current_family_id');
    }

    public function membershipIn(Family|int $family): ?FamilyMember
    {
        $id = $family instanceof Family ? $family->id : $family;

        return FamilyMember::where('family_id', $id)->where('user_id', $this->id)->first();
    }
}
