<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    use RecordsActivity;

    public const TYPES = ['migration' => 'Migration / move', 'residence' => 'Residence', 'education' => 'Education', 'occupation' => 'Work', 'military' => 'Military service', 'religious' => 'Religious ceremony', 'award' => 'Award / achievement', 'illness' => 'Illness / health', 'celebration' => 'Celebration', 'custom' => 'Other'];

    protected $guarded = [];

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'event_person')->withPivot('role');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function fromPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'from_place_id');
    }
}
