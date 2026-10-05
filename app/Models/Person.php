<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\PartialDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Person extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $table = 'persons';

    protected $guarded = [];

    protected $casts = ['is_living' => 'boolean'];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function names(): HasMany
    {
        return $this->hasMany(PersonName::class);
    }

    public function birthPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'birth_place_id');
    }

    public function deathPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'death_place_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }

    public function facts(): HasMany
    {
        return $this->hasMany(Fact::class);
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_person');
    }

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'person_story');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_person')->withPivot('role');
    }

    /** Links where this person is the child, i.e. this person's parents. */
    public function parentLinks(): HasMany
    {
        return $this->hasMany(ParentChild::class, 'child_id');
    }

    /** Links where this person is the parent, i.e. this person's children. */
    public function childLinks(): HasMany
    {
        return $this->hasMany(ParentChild::class, 'parent_id');
    }

    /** All partnerships this person is part of, oldest first. */
    public function partnerships(): Collection
    {
        return Partnership::where(fn ($q) => $q->where('partner1_id', $this->id)->orWhere('partner2_id', $this->id))
            ->with(['partner1', 'partner2'])
            ->get()
            ->sortBy(fn ($p) => PartialDate::sortKey($p->start_date))
            ->values();
    }

    /** Parents, each with its ParentChild link available as ->link. */
    public function parents(): Collection
    {
        return $this->parentLinks()->with('parent')->get()
            ->filter(fn ($l) => $l->parent)
            ->map(fn ($l) => $l->parent->setRelation('link', $l))->values();
    }

    public function children(): Collection
    {
        return $this->childLinks()->with('child')->get()
            ->filter(fn ($l) => $l->child)
            ->map(fn ($l) => $l->child->setRelation('link', $l))
            ->sortBy(fn ($c) => PartialDate::sortKey($c->birth_date))->values();
    }

    /**
     * Siblings classified as full / half / step / adoptive, derived from the graph.
     *
     * @return Collection<int, array{person: Person, kind: string}>
     */
    public function siblings(): Collection
    {
        $myLinks = $this->parentLinks()->get();
        if ($myLinks->isEmpty()) {
            return collect();
        }
        $myBio = $myLinks->where('type', 'biological')->pluck('parent_id');
        $sibLinks = ParentChild::whereIn('parent_id', $myLinks->pluck('parent_id'))
            ->where('child_id', '!=', $this->id)->with('child')->get();

        return $sibLinks->groupBy('child_id')->map(function ($links) use ($myLinks, $myBio) {
            $sharedBio = $links->where('type', 'biological')->pluck('parent_id')->intersect($myBio)->count();
            $allBioMine = $myBio->count();
            $kind = match (true) {
                $sharedBio >= 2 || ($sharedBio === 1 && $allBioMine === 1 && $links->where('type', 'biological')->count() === 1) => 'full',
                $sharedBio === 1 => 'half',
                $links->contains('type', 'step') || $myLinks->contains('type', 'step') => 'step',
                default => 'adoptive',
            };

            return ['person' => $links->first()->child, 'kind' => $kind];
        })->filter(fn ($s) => $s['person'])
            ->sortBy(fn ($s) => PartialDate::sortKey($s['person']->birth_date))->values();
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->given_name.' '.$this->surname);
    }

    public function getLifespanAttribute(): string
    {
        $b = PartialDate::year($this->birth_date);
        $d = PartialDate::year($this->death_date);
        if (! $b && ! $d) {
            return $this->is_living ? '' : 'Deceased';
        }

        if ($this->is_living && ! $d) {
            return 'b. '.$b;
        }

        return ($b ?: '?').'–'.($d ?: '?');
    }

    public function getAgeAttribute(): ?int
    {
        return PartialDate::age($this->birth_date, $this->is_living ? null : $this->death_date);
    }

    public function getInitialsAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->given_name, 0, 1).mb_substr((string) $this->surname, 0, 1));
    }

    /** 0–100 score used to nudge the family to fill gaps. */
    public function completeness(): array
    {
        $checks = [
            'birth date' => (bool) $this->birth_date,
            'birthplace' => (bool) $this->birth_place_id,
            'photo' => (bool) $this->photo_media_id,
            'biography' => strlen((string) $this->biography) > 40,
            'parents' => $this->parentLinks()->exists(),
            'a story' => $this->stories()->exists(),
            'occupation' => (bool) $this->occupation,
        ];
        if (! $this->is_living) {
            $checks['death date'] = (bool) $this->death_date;
        }

        return [
            'score' => (int) round(count(array_filter($checks)) / count($checks) * 100),
            'missing' => array_keys(array_filter($checks, fn ($v) => ! $v)),
        ];
    }

    public function scopeVisibleTo(Builder $q, ?FamilyMember $member): Builder
    {
        if ($member && $member->atLeast('admin')) {
            return $q;
        }
        $q->where('privacy', 'family');
        if ($member?->role === 'viewer' && $member->family->hide_living_from_viewers) {
            $q->where('is_living', false);
        }

        return $q;
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $q->where(fn ($w) => $w->where('given_name', 'like', $like)
            ->orWhere('surname', 'like', $like)
            ->orWhere('nickname', 'like', $like)
            ->orWhereRaw("(given_name || ' ' || coalesce(surname, '')) like ?", [$like])
            ->orWhereHas('names', fn ($n) => $n->where('full_name', 'like', $like)));
    }
}
