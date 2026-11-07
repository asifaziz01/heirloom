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

#[Fillable(['name', 'email', 'phone', 'password', 'current_family_id'])]
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

    /**
     * Mobile numbers are stored as digits with an optional leading "+", so that
     * "+91 98765-43210", "(091) 98765 43210" and "+919876543210" all match.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '' || $digits === null) {
            return null;
        }
        if (str_starts_with($phone, '+') || str_starts_with($digits, '00')) {
            $digits = '+'.ltrim($digits, '0');
        }

        return $digits;
    }

    /** Which column to match for a value typed into the "email or mobile" field. */
    public static function credentialsFor(string $login): array
    {
        $login = trim($login);
        if (str_contains($login, '@')) {
            return ['email' => mb_strtolower($login)];
        }

        return ['phone' => self::normalizePhone($login) ?? $login];
    }

    /** How to address the user when there is no email, e.g. on the members page. */
    public function contact(): ?string
    {
        return $this->email ?? $this->phone;
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
