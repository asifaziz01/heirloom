<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentChild extends Model
{
    use RecordsActivity;

    public const TYPES = ['biological' => 'Biological', 'adoptive' => 'Adoptive', 'step' => 'Step', 'foster' => 'Foster', 'guardian' => 'Guardian', 'unknown' => 'Unknown'];

    protected $table = 'parent_child';

    protected $guarded = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'parent_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'child_id');
    }

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }
}
