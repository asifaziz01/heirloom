<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\PartialDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Partnership extends Model
{
    use RecordsActivity;

    public const TYPES = ['marriage' => 'Marriage', 'civil' => 'Civil union', 'partnership' => 'Partnership', 'engagement' => 'Engagement', 'unknown' => 'Unknown'];

    public const STATUSES = ['current' => 'Current', 'divorced' => 'Divorced', 'separated' => 'Separated', 'widowed' => 'Widowed', 'annulled' => 'Annulled', 'ended' => 'Ended'];

    protected $guarded = [];

    public function partner1(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'partner1_id');
    }

    public function partner2(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'partner2_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function childLinks(): HasMany
    {
        return $this->hasMany(ParentChild::class);
    }

    public function partnerOf(Person|int $person): ?Person
    {
        $id = $person instanceof Person ? $person->id : $person;

        return $this->partner1_id === $id ? $this->partner2 : $this->partner1;
    }

    /** Distinct children linked to this partnership, oldest first. */
    public function children(): Collection
    {
        return Person::whereIn('id', $this->childLinks()->pluck('child_id'))->get()
            ->sortBy(fn ($c) => PartialDate::sortKey($c->birth_date))->values();
    }

    public function label(): string
    {
        $parts = [self::TYPES[$this->type] ?? $this->type];
        if ($this->status !== 'current') {
            $parts[] = strtolower(self::STATUSES[$this->status] ?? $this->status);
        }
        if ($this->start_date) {
            $parts[] = PartialDate::year($this->start_date).($this->end_date ? '–'.PartialDate::year($this->end_date) : '');
        }

        return implode(' · ', $parts);
    }
}
