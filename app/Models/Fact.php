<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\PartialDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fact extends Model
{
    use RecordsActivity;

    public const FIELDS = ['birth_date' => 'Date of birth', 'death_date' => 'Date of death', 'birth_place' => 'Place of birth', 'death_place' => 'Place of death', 'occupation' => 'Occupation', 'name' => 'Name', 'other' => 'Other'];

    public const CONFIDENCE = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];

    public const STATUSES = ['verified' => 'Verified', 'unverified' => 'Unverified', 'disputed' => 'Disputed'];

    protected $guarded = [];

    protected $casts = ['is_preferred' => 'boolean'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function displayValue(): string
    {
        if (in_array($this->field, ['birth_date', 'death_date'], true)) {
            return PartialDate::format(PartialDate::normalize($this->value)) ?: $this->value;
        }

        return $this->value;
    }
}
