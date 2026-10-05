<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Family;
use App\Models\Media;
use App\Models\ParentChild;
use App\Models\Partnership;
use App\Models\Person;
use App\Models\Story;
use App\Support\PartialDate;
use Illuminate\Support\Collection;

/**
 * Chronological family history assembled from births, deaths, partnerships, life events,
 * dated stories and dated photos/documents.
 */
class Timeline
{
    public static function forFamily(Family $family, ?array $personIds = null): Collection
    {
        $items = collect();
        $people = $family->persons()->with(['birthPlace', 'deathPlace'])
            ->when($personIds, fn ($q) => $q->whereIn('id', $personIds))->get();

        foreach ($people as $p) {
            if ($p->birth_date) {
                $items->push(self::item($p->birth_date, 'birth', "{$p->full_name} born", [$p], $p->birthPlace?->label, route('people.show', [$family, $p]), $p->birth_qualifier));
            }
            if ($p->death_date) {
                $items->push(self::item($p->death_date, 'death', "{$p->full_name} died", [$p], $p->deathPlace?->label, route('people.show', [$family, $p]), $p->death_qualifier));
            }
        }

        $partnerships = Partnership::where('family_id', $family->id)->with(['partner1', 'partner2', 'place'])
            ->when($personIds, fn ($q) => $q->where(fn ($w) => $w->whereIn('partner1_id', $personIds)->orWhereIn('partner2_id', $personIds)))->get();
        foreach ($partnerships as $u) {
            $names = collect([$u->partner1, $u->partner2])->filter();
            $verb = $u->type === 'engagement' ? 'engaged' : ($u->type === 'marriage' ? 'married' : 'partnered');
            if ($u->start_date) {
                $items->push(self::item($u->start_date, 'marriage', $names->pluck('full_name')->join(' & ')." {$verb}", $names->all(), $u->place?->label));
            }
            if ($u->end_date && in_array($u->status, ['divorced', 'separated', 'annulled', 'ended'], true)) {
                $items->push(self::item($u->end_date, 'divorce', $names->pluck('full_name')->join(' & ').' '.$u->status, $names->all()));
            }
        }

        $events = Event::where('family_id', $family->id)->with(['people', 'place', 'fromPlace'])->whereNotNull('date')
            ->when($personIds, fn ($q) => $q->whereHas('people', fn ($w) => $w->whereIn('persons.id', $personIds)))->get();
        foreach ($events as $e) {
            $where = $e->type === 'migration' && $e->fromPlace ? $e->fromPlace->label.' → '.($e->place?->label ?? '?') : $e->place?->label;
            $items->push(self::item($e->date, $e->type, $e->title, $e->people->all(), $where, route('events.edit', [$family, $e]), $e->date_qualifier, $e->description));
        }

        $stories = Story::where('family_id', $family->id)->with('people')->whereNotNull('story_date')
            ->when($personIds, fn ($q) => $q->whereHas('people', fn ($w) => $w->whereIn('persons.id', $personIds)))->get();
        foreach ($stories as $s) {
            $items->push(self::item($s->story_date, 'story', $s->title, $s->people->all(), null, route('stories.show', [$family, $s]), 'exact', $s->excerpt(140)));
        }

        $media = Media::where('family_id', $family->id)->with('people')->whereNotNull('taken_date')
            ->when($personIds, fn ($q) => $q->whereHas('people', fn ($w) => $w->whereIn('persons.id', $personIds)))->get();
        foreach ($media as $m) {
            $item = self::item($m->taken_date, $m->kind, $m->display_title, $m->people->all(), null, route('media.show', [$family, $m]));
            $item['media'] = $m;
            $items->push($item);
        }

        return $items->sortBy('sort')->values();
    }

    /** A person's life: their own events plus births of children and deaths of parents/partners. */
    public static function forPerson(Person $person): Collection
    {
        $family = $person->family;
        $ids = [$person->id];
        $items = self::forFamily($family, $ids);

        $childIds = ParentChild::where('parent_id', $person->id)->pluck('child_id');
        $parentIds = ParentChild::where('child_id', $person->id)->pluck('parent_id');
        $relatives = Person::whereIn('id', $childIds->merge($parentIds))->get();
        foreach ($relatives as $r) {
            if ($childIds->contains($r->id) && $r->birth_date) {
                $items->push(self::item($r->birth_date, 'birth', 'Birth of '.($r->gender === 'male' ? 'son' : ($r->gender === 'female' ? 'daughter' : 'child')).' '.$r->given_name, [$r], null, route('people.show', [$family, $r])) + ['relative' => true]);
            }
            if ($parentIds->contains($r->id) && $r->death_date) {
                $items->push(self::item($r->death_date, 'death', 'Death of '.($r->gender === 'male' ? 'father' : ($r->gender === 'female' ? 'mother' : 'parent')).' '.$r->given_name, [$r], null, route('people.show', [$family, $r])) + ['relative' => true]);
            }
        }

        return $items->map(function ($i) use ($person) {
            $i['age'] = $person->birth_date && $i['date'] >= $person->birth_date ? PartialDate::age($person->birth_date, $i['date']) : null;

            return $i;
        })->sortBy('sort')->values();
    }

    public static function item(string $date, string $type, string $title, array $people = [], ?string $place = null, ?string $url = null, ?string $qualifier = 'exact', ?string $detail = null): array
    {
        return [
            'date' => $date,
            'label' => PartialDate::format($date, $qualifier),
            'year' => PartialDate::year($date),
            'sort' => PartialDate::sortKey($date),
            'type' => $type,
            'title' => $title,
            'people' => $people,
            'place' => $place,
            'url' => $url,
            'detail' => $detail,
        ];
    }
}
