<?php

namespace App\Services;

use App\Models\Family;
use App\Models\ParentChild;
use App\Models\Partnership;
use App\Models\Person;
use App\Support\PartialDate;

/**
 * Derives visual tree structures (descendant chart, pedigree chart) from the relationship graph.
 * Loads the whole family once and walks it in memory, so charts cost a fixed number of queries.
 */
class TreeBuilder
{
    /** @var array<int, Person> */
    public array $people;

    /** @var array<int, ParentChild[]> */
    private array $linksByParent = [];

    /** @var array<int, ParentChild[]> */
    private array $linksByChild = [];

    /** @var array<int, Partnership[]> */
    private array $partnershipsByPerson = [];

    public function __construct(Family $family)
    {
        $this->people = $family->persons()->with(['photo', 'birthPlace'])->get()->keyBy('id')->all();
        foreach (ParentChild::where('family_id', $family->id)->get() as $l) {
            $this->linksByParent[$l->parent_id][] = $l;
            $this->linksByChild[$l->child_id][] = $l;
        }
        foreach (Partnership::where('family_id', $family->id)->get() as $p) {
            $this->partnershipsByPerson[$p->partner1_id][] = $p;
            if ($p->partner2_id) {
                $this->partnershipsByPerson[$p->partner2_id][] = $p;
            }
        }
    }

    /**
     * Nested descendant structure. Children are grouped under the partnership they belong to,
     * so a person with several marriages shows each family unit separately.
     */
    public function descendants(int $rootId, int $depth = 4, array &$seen = []): ?array
    {
        $person = $this->people[$rootId] ?? null;
        if (! $person || isset($seen[$rootId])) {
            return null;
        }
        $seen[$rootId] = true;
        $node = ['person' => $person, 'unions' => [], 'more' => false];
        $childLinks = collect($this->linksByParent[$rootId] ?? []);
        if ($depth <= 0) {
            $node['more'] = $childLinks->isNotEmpty();

            return $node;
        }
        $partnerships = collect($this->partnershipsByPerson[$rootId] ?? [])->sortBy(fn ($p) => PartialDate::sortKey($p->start_date));
        $used = [];
        foreach ($partnerships as $p) {
            $partnerId = $p->partner1_id === $rootId ? $p->partner2_id : $p->partner1_id;
            $kids = $childLinks->where('partnership_id', $p->id);
            $node['unions'][] = [
                'partnership' => $p,
                'partner' => $partnerId ? ($this->people[$partnerId] ?? null) : null,
                'children' => $this->childNodes($kids, $depth, $seen),
            ];
            $used = array_merge($used, $kids->pluck('child_id')->all());
        }
        $rest = $childLinks->whereNotIn('child_id', $used);
        if ($rest->isNotEmpty()) {
            $node['unions'][] = ['partnership' => null, 'partner' => null, 'children' => $this->childNodes($rest, $depth, $seen)];
        }

        return $node;
    }

    private function childNodes($links, int $depth, array &$seen): array
    {
        return $links->sortBy(fn ($l) => PartialDate::sortKey($this->people[$l->child_id]->birth_date ?? null))
            ->map(function ($l) use ($depth, &$seen) {
                $n = $this->descendants($l->child_id, $depth - 1, $seen);
                if ($n) {
                    $n['link_type'] = $l->type;
                }

                return $n;
            })->filter()->values()->all();
    }

    /**
     * Pedigree slots: generation g has 2^g slots; slot 2i is the father side, 2i+1 the mother side.
     * Kinship (biological, then adoptive) parents are preferred over step/foster links.
     *
     * @return array<int, array<int, ?Person>>
     */
    public function pedigree(int $rootId, int $generations = 5): array
    {
        $rows = [[$this->people[$rootId] ?? null]];
        for ($g = 1; $g < $generations; $g++) {
            $row = [];
            foreach ($rows[$g - 1] as $child) {
                [$f, $m] = $child ? $this->primaryParents($child->id) : [null, null];
                $row[] = $f;
                $row[] = $m;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array{0: ?Person, 1: ?Person} */
    public function primaryParents(int $childId): array
    {
        $rank = ['biological' => 0, 'adoptive' => 1, 'unknown' => 2, 'guardian' => 3, 'foster' => 4, 'step' => 5];
        $links = collect($this->linksByChild[$childId] ?? [])->sortBy(fn ($l) => $rank[$l->type] ?? 9)->values();
        $parents = $links->map(fn ($l) => $this->people[$l->parent_id] ?? null)->filter()->values();
        $father = $parents->firstWhere('gender', 'male');
        $mother = $parents->firstWhere('gender', 'female');
        $others = $parents->reject(fn ($p) => $p === $father || $p === $mother)->values();
        $father ??= $others->shift();
        $mother ??= $others->shift();

        return [$father, $mother];
    }

    /** Topmost ancestors (people with no parents in the tree) ordered by descendant count. */
    public function rootCandidates(int $limit = 8): array
    {
        $roots = collect($this->people)->filter(fn ($p) => empty($this->linksByChild[$p->id]));

        return $roots->map(fn ($p) => ['person' => $p, 'count' => $this->countDescendants($p->id)])
            ->sortByDesc('count')->take($limit)->values()->all();
    }

    public function countDescendants(int $id, array &$seen = []): int
    {
        $n = 0;
        foreach ($this->linksByParent[$id] ?? [] as $l) {
            // Only blood/adoptive lines count as descendants; step and foster links do not.
            if (in_array($l->type, ['biological', 'adoptive', 'unknown'], true) && ! isset($seen[$l->child_id])) {
                $seen[$l->child_id] = true;
                $n += 1 + $this->countDescendants($l->child_id, $seen);
            }
        }

        return $n;
    }

    /** Generation number per person, counted from the oldest root ancestors (1 = oldest). */
    public function generations(): array
    {
        $gen = [];
        $queue = [];
        foreach ($this->people as $id => $p) {
            if (empty($this->linksByChild[$id])) {
                $gen[$id] = 1;
                $queue[] = $id;
            }
        }
        while ($queue) {
            $id = array_shift($queue);
            foreach ($this->linksByParent[$id] ?? [] as $l) {
                $g = $gen[$id] + 1;
                if (! isset($gen[$l->child_id]) || $gen[$l->child_id] < $g) {
                    $gen[$l->child_id] = $g;
                    $queue[] = $l->child_id;
                }
            }
        }
        // Partners who married in take their spouse's generation.
        foreach ($this->partnershipsByPerson as $id => $ps) {
            if (! empty($this->linksByChild[$id])) {
                continue;
            }
            foreach ($ps as $p) {
                $other = $p->partner1_id === $id ? $p->partner2_id : $p->partner1_id;
                if ($other && isset($gen[$other]) && ! empty($this->linksByChild[$other])) {
                    $gen[$id] = $gen[$other];
                }
            }
        }

        return $gen;
    }
}
