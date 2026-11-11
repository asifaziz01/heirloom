<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something the family is planning or looking forward to. Unlike a life event
 * (which records the past), an occasion has a real calendar date, can repeat every
 * year, takes RSVPs and sends reminders.
 */
class Occasion extends Model
{
    use RecordsActivity;

    public const TYPES = [
        'wedding' => 'Wedding',
        'engagement' => 'Engagement',
        'anniversary' => 'Anniversary celebration',
        'birthday' => 'Birthday celebration',
        'naming' => 'Naming / aqiqah / christening',
        'graduation' => 'Graduation',
        'housewarming' => 'Housewarming',
        'reunion' => 'Family reunion / get-together',
        'religious' => 'Religious festival / ceremony',
        'memorial' => 'Remembrance / death anniversary',
        'other' => 'Other',
    ];

    public const ICONS = [
        'wedding' => '💍', 'engagement' => '💐', 'anniversary' => '🥂', 'birthday' => '🎂', 'naming' => '👶',
        'graduation' => '🎓', 'housewarming' => '🏡', 'reunion' => '👨‍👩‍👧‍👦', 'religious' => '🌙', 'memorial' => '🕯️', 'other' => '📅',
    ];

    /** Days before an occasion on which a reminder email goes out (0 = the morning of). */
    public const REMINDER_DAYS = [7, 1, 0];

    public const RSVP_STATUSES = ['going' => 'Going', 'maybe' => 'Maybe', 'no' => 'Can’t make it'];

    protected $guarded = [];

    protected $casts = [
        'date' => 'immutable_date',
        'end_date' => 'immutable_date',
        'repeats_yearly' => 'boolean',
        'rsvp_enabled' => 'boolean',
        'send_reminders' => 'boolean',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'occasion_person');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(OccasionRsvp::class);
    }

    public function rsvpOf(?User $user): ?OccasionRsvp
    {
        return $user ? $this->rsvps->firstWhere('user_id', $user->id) : null;
    }

    /** Headcount per answer, counting the extra guests people bring along. */
    public function headcount(): array
    {
        $out = ['going' => 0, 'maybe' => 0, 'no' => 0];
        foreach ($this->rsvps as $r) {
            $out[$r->status] += 1 + ($r->status === 'going' ? $r->guests : 0);
        }

        return $out;
    }

    public function icon(): string
    {
        return self::ICONS[$this->type] ?? self::ICONS['other'];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    /**
     * The next calendar date this occasion falls on, on or after $from (today by default).
     * A one-off occasion that has passed returns null; a yearly one rolls forward.
     */
    public function nextOccurrence(?CarbonImmutable $from = null): ?CarbonImmutable
    {
        $from ??= CarbonImmutable::today();
        $last = $this->end_date ?? $this->date;
        if ($last->gte($from)) {
            return $this->date->lt($from) ? $from : $this->date; // running right now, or still ahead
        }
        if (! $this->repeats_yearly) {
            return null;
        }
        $candidate = $this->date->setYear($from->year);
        if ($candidate->lt($from)) {
            $candidate = $candidate->addYear();
        }

        return $candidate;
    }

    /** How many times it has come round, e.g. 25 for a silver anniversary; null the first time. */
    public function yearsSince(?CarbonImmutable $on = null): ?int
    {
        $on ??= $this->nextOccurrence() ?? $this->date;
        $years = $on->year - $this->date->year;

        return $this->repeats_yearly && $years > 0 ? $years : null;
    }

    /** "25th", "1st", "112th". */
    public static function ordinal(int $n): string
    {
        $suffix = ($n % 100 >= 11 && $n % 100 <= 13) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');

        return $n.$suffix;
    }

    public function isPast(): bool
    {
        return $this->nextOccurrence() === null;
    }

    public function startsAt(): CarbonImmutable
    {
        $date = $this->nextOccurrence() ?? $this->date;

        return $this->time ? $date->setTimeFromTimeString($this->time) : $date;
    }

    /** "Sat 20 Dec 2026, 6:30 pm" or "20–22 Dec 2026". */
    public function whenLabel(): string
    {
        $date = $this->nextOccurrence() ?? $this->date;
        if ($this->end_date && $this->end_date->gt($this->date)) {
            $end = $this->end_date->addYears($date->year - $this->date->year);

            return $date->format($date->month === $end->month ? 'j' : 'j M').'–'.$end->format('j M Y');
        }
        $label = $date->format('D j M Y');

        return $this->time ? $label.', '.$date->setTimeFromTimeString($this->time)->format('g:i a') : $label;
    }

    public function whereLabel(): ?string
    {
        $where = collect([$this->venue, $this->place?->label])->filter()->unique()->implode(', ');

        return $where !== '' ? $where : null;
    }

    /** Still ahead, or repeating: everything that belongs on the "Coming up" list. */
    public function scopeCurrent(Builder $q): Builder
    {
        $today = CarbonImmutable::today()->toDateString();

        return $q->where(fn ($w) => $w->where('repeats_yearly', true)
            ->orWhere('date', '>=', $today)
            ->orWhere('end_date', '>=', $today));
    }

    public function scopePast(Builder $q): Builder
    {
        $today = CarbonImmutable::today()->toDateString();

        return $q->where('repeats_yearly', false)->where('date', '<', $today)
            ->where(fn ($w) => $w->whereNull('end_date')->orWhere('end_date', '<', $today));
    }

    /** Short line for emails and the WhatsApp share text. */
    public function summaryLine(): string
    {
        return trim($this->icon().' '.$this->title.' · '.$this->whenLabel().($this->whereLabel() ? ' · '.$this->whereLabel() : ''));
    }
}
