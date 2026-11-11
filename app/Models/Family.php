<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Family extends Model
{
    protected $guarded = [];

    protected $casts = ['hide_living_from_viewers' => 'boolean', 'is_public' => 'boolean'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'family_members')->withPivot('role', 'person_id');
    }

    public function persons(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    } // scoped route binding for {person}

    public function partnerships(): HasMany
    {
        return $this->hasMany(Partnership::class);
    }

    public function parentLinks(): HasMany
    {
        return $this->hasMany(ParentChild::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function occasions(): HasMany
    {
        return $this->hasMany(Occasion::class);
    }

    /** Members with an email address who have not switched reminder emails off. */
    public function reminderRecipients(): Collection
    {
        return $this->users()->whereNotNull('users.email')->wherePivot('email_reminders', true)->get();
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function facts(): HasMany
    {
        return $this->hasMany(Fact::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function aiTasks(): HasMany
    {
        return $this->hasMany(AiTask::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(Export::class);
    }

    public function planConfig(): array
    {
        return config('plans.'.$this->plan, config('plans.free'));
    }

    public function hasFeature(string $feature): bool
    {
        return ! config('services.heritage.billing') || in_array($feature, $this->planConfig()['features'], true);
    }

    /** Returns null when within the limit, or a human-readable reason when it is reached. */
    public function limitReached(string $limit, int $adding = 1): ?string
    {
        if (! config('services.heritage.billing')) {
            return null;
        }
        $plan = $this->planConfig();
        $max = $plan[$limit] ?? null;
        if ($max === null) {
            return null;
        }
        $current = match ($limit) {
            'people' => $this->persons()->count(),
            'photos' => $this->media()->where('kind', 'photo')->count(),
            'storage_mb' => (int) ceil($this->storage_used / 1048576),
            'contributors' => $this->members()->whereIn('role', ['admin', 'editor', 'contributor'])->count(),
        };
        $label = ['people' => 'people', 'photos' => 'photos', 'storage_mb' => 'MB of storage', 'contributors' => 'contributors'][$limit];

        return $current + $adding > $max ? "The {$plan['name']} plan allows {$max} {$label}. Upgrade to add more." : null;
    }
}
