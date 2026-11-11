<?php

namespace App\Services;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Occasion;
use App\Models\Partnership;
use App\Models\Person;
use App\Support\PartialDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What is coming up for a family: birthdays of the living, wedding anniversaries of
 * couples still together, remembrance days of those who have passed, and the
 * occasions the family has planned. Feeds the home page, the weekly digest email
 * and the calendar feed.
 *
 * Each item is an array with 'when' (CarbonImmutable), 'type' and type-specific keys:
 *   birthday / remembrance → person, years
 *   anniversary            → partnership, years
 *   occasion               → occasion, years (null unless it repeats)
 */
class Upcoming
{
    public static function forFamily(Family $family, ?FamilyMember $member, int $days = 30, ?CarbonImmutable $from = null): Collection
    {
        $people = $family->persons()->visibleTo($member)->get();

        return self::build($family, $people, $days, $from ?? CarbonImmutable::today());
    }

    public static function build(Family $family, Collection $people, int $days, CarbonImmutable $today): Collection
    {
        $items = collect();
        $next = function (string $date) use ($today, $days): ?CarbonImmutable {
            if (! PartialDate::isComplete($date)) {
                return null;
            }
            [$y, $m, $d] = array_map('intval', explode('-', $date));
            if ($m === 2 && $d === 29 && ! $today->isLeapYear()) {
                $d = 28;
            }
            $candidate = $today->setDate($today->year, $m, $d);
            if ($candidate->lt($today)) {
                $candidate = $candidate->addYear();
            }

            return $candidate->diffInDays($today, true) <= $days ? $candidate : null;
        };

        foreach ($people as $p) {
            if ($p->is_living && $p->birth_date && ($when = $next($p->birth_date))) {
                $items->push(['when' => $when, 'type' => 'birthday', 'person' => $p, 'years' => $when->year - PartialDate::year($p->birth_date)]);
            }
            if (! $p->is_living && $p->death_date && ($when = $next($p->death_date))) {
                $items->push(['when' => $when, 'type' => 'remembrance', 'person' => $p, 'years' => $when->year - PartialDate::year($p->death_date)]);
            }
        }

        $ids = $people->pluck('id');
        foreach (Partnership::where('family_id', $family->id)->where('status', 'current')->whereNotNull('start_date')->with(['partner1', 'partner2'])->get() as $u) {
            $bothLiving = $u->partner1?->is_living && $u->partner2?->is_living;
            if ($bothLiving && $ids->contains($u->partner1_id) && ($when = $next($u->start_date))) {
                $items->push(['when' => $when, 'type' => 'anniversary', 'partnership' => $u, 'years' => $when->year - PartialDate::year($u->start_date)]);
            }
        }

        foreach ($family->occasions()->current()->with(['people', 'place'])->get() as $o) {
            $when = $o->nextOccurrence($today);
            if ($when && $when->diffInDays($today, true) <= $days) {
                $items->push(['when' => $when, 'type' => 'occasion', 'occasion' => $o, 'years' => $o->yearsSince($when)]);
            }
        }

        return $items->sortBy(fn ($i) => $i['when']->format('Y-m-d').($i['type'] === 'occasion' ? '0' : '1'))->values();
    }

    /** One plain-text line per item, for emails and messages. */
    public static function describe(array $item): string
    {
        return match ($item['type']) {
            'birthday' => "🎂 {$item['person']->full_name} turns {$item['years']}",
            'anniversary' => '💍 '.collect([$item['partnership']->partner1?->given_name, $item['partnership']->partner2?->given_name])->filter()->join(' & ')." – {$item['years']} years together",
            'remembrance' => "🕯️ Remembering {$item['person']->full_name} – {$item['years']} years",
            'occasion' => $item['occasion']->icon().' '.$item['occasion']->title.($item['years'] ? " ({$item['years']} years)" : ''),
        };
    }

    /** Where the item leads in the app. */
    public static function url(Family $family, array $item): string
    {
        return match ($item['type']) {
            'birthday', 'remembrance' => route('people.show', [$family, $item['person']]),
            'anniversary' => route('partnerships.edit', [$family, $item['partnership']]),
            'occasion' => route('occasions.show', [$family, $item['occasion']]),
        };
    }
}
