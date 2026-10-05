<?php

namespace App\Services;

use App\Models\Family;
use App\Models\ParentChild;
use App\Models\Partnership;
use App\Models\Person;

/**
 * Answers "How is B related to A?" over the relationship graph:
 * blood kinship via common ancestors (incl. cousins n-times removed and half relations),
 * partners, step relations and in-laws, plus the shortest connecting path.
 */
class RelationshipCalculator
{
    /** @var array<int, array<int, string>> child_id => [parent_id => type] */
    private array $parents = [];

    /** @var array<int, array<int, int>> parent_id => [child_id] */
    private array $children = [];

    /** @var array<int, array<int, string>> person_id => [partner_id => status] */
    private array $partners = [];

    /** @var array<int, Person> */
    private array $people = [];

    private const KIN_TYPES = ['biological', 'adoptive', 'unknown'];

    public function __construct(Family $family)
    {
        $this->people = $family->persons()->get()->keyBy('id')->all();
        foreach (ParentChild::where('family_id', $family->id)->get() as $l) {
            $this->parents[$l->child_id][$l->parent_id] = $l->type;
            $this->children[$l->parent_id][] = $l->child_id;
        }
        foreach (Partnership::where('family_id', $family->id)->whereNotNull('partner2_id')->get() as $p) {
            $this->partners[$p->partner1_id][$p->partner2_id] = $p->status;
            $this->partners[$p->partner2_id][$p->partner1_id] = $p->status;
        }
    }

    /** @return array{label: string, sentence: string, path: array, common: array} */
    public function describe(int $aId, int $bId): array
    {
        $a = $this->people[$aId];
        $b = $this->people[$bId];
        $path = $this->path($aId, $bId);
        $base = ['path' => $path, 'common' => []];

        if ($aId === $bId) {
            return $base + ['label' => 'same person', 'sentence' => 'That is the same person.'];
        }

        $label = $this->bloodLabel($aId, $bId, $common) ?? $this->affinityLabel($aId, $bId);
        if ($common) {
            $base['common'] = array_map(fn ($id) => $this->people[$id], $common);
        }
        if (! $label) {
            $label = $path ? 'distant relative by marriage' : 'not connected in this tree';
        }

        return $base + [
            'label' => $label,
            'sentence' => $path || $label !== 'not connected in this tree'
                ? "{$b->full_name} is {$a->full_name}’s {$label}."
                : "{$b->full_name} and {$a->full_name} are not connected in this tree yet.",
        ];
    }

    /** Ancestors with generation distance, following kinship links only. */
    public function ancestors(int $id): array
    {
        $dist = [];
        $frontier = [$id => 0];
        while ($frontier) {
            $next = [];
            foreach ($frontier as $pid => $d) {
                foreach ($this->parents[$pid] ?? [] as $parentId => $type) {
                    if (! in_array($type, self::KIN_TYPES, true) || isset($dist[$parentId])) {
                        continue;
                    }
                    $dist[$parentId] = $d + 1;
                    $next[$parentId] = $d + 1;
                }
            }
            $frontier = $next;
        }

        return $dist;
    }

    private function bloodLabel(int $aId, int $bId, ?array &$commonOut = null): ?string
    {
        $commonOut = [];
        $ancA = $this->ancestors($aId);
        $ancB = $this->ancestors($bId);
        $g = $this->people[$bId]->gender;

        if (isset($ancA[$bId])) {
            return $this->lineal($ancA[$bId], $g, up: true);
        }
        if (isset($ancB[$aId])) {
            return $this->lineal($ancB[$aId], $g, up: false);
        }

        $common = array_intersect_key($ancA, $ancB);
        if (! $common) {
            return null;
        }
        $best = null;
        foreach ($common as $id => $_) {
            $sum = $ancA[$id] + $ancB[$id];
            if ($best === null || $sum < $best) {
                $best = $sum;
            }
        }
        $closest = array_keys(array_filter($common, fn ($_, $id) => $best === $ancA[$id] + $ancB[$id], ARRAY_FILTER_USE_BOTH));
        $commonOut = $closest;
        $da = $ancA[$closest[0]];
        $db = $ancB[$closest[0]];
        $half = count($closest) === 1 && count($this->parents[$aId] ?? []) >= 2 && count($this->parents[$bId] ?? []) >= 2;

        $label = match (true) {
            $da === 1 && $db === 1 => $this->gendered($g, 'brother', 'sister', 'sibling'),
            // nephew, grandnephew, great-grandnephew ...
            $da === 1 => ($db === 2 ? '' : $this->greatPrefix($db - 3).'grand').$this->gendered($g, 'nephew', 'niece', 'nibling'),
            // uncle, great-uncle, great-great-uncle ...
            $db === 1 => $this->greatPrefix($da - 2).$this->gendered($g, 'uncle', 'aunt', 'parent’s sibling'),
            default => $this->cousin(min($da, $db) - 1, abs($da - $db)),
        };

        return ($half ? 'half-' : '').$label;
    }

    private function affinityLabel(int $aId, int $bId): ?string
    {
        $g = $this->people[$bId]->gender;
        if (isset($this->partners[$aId][$bId])) {
            $status = $this->partners[$aId][$bId];
            $word = $this->gendered($g, 'husband', 'wife', 'spouse');

            return in_array($status, ['divorced', 'annulled', 'separated', 'ended'], true) ? "former {$word}" : ($status === 'widowed' ? "late {$word}" : $word);
        }
        // Direct non-kinship parent links: step / foster / guardian
        $prefix = ['step' => 'step', 'foster' => 'foster ', 'guardian' => 'guardian'];
        if (($t = $this->parents[$aId][$bId] ?? null) && isset($prefix[$t])) {
            return $t === 'guardian' ? 'guardian' : $prefix[$t].$this->gendered($g, 'father', 'mother', 'parent');
        }
        if (($t = $this->parents[$bId][$aId] ?? null) && isset($prefix[$t])) {
            return $t === 'guardian' ? 'ward' : $prefix[$t].$this->gendered($g, 'son', 'daughter', 'child');
        }
        // B is a partner of A's parent (but not A's parent) => step-parent
        foreach (array_keys($this->parents[$aId] ?? []) as $p) {
            if (isset($this->partners[$p][$bId]) && ! isset($this->parents[$aId][$bId])) {
                return 'step-'.$this->gendered($g, 'father', 'mother', 'parent');
            }
        }
        // B is a child of A's partner (but not A's child) => stepchild
        foreach (array_keys($this->partners[$aId] ?? []) as $p) {
            if (in_array($bId, $this->children[$p] ?? [], true) && ! in_array($bId, $this->children[$aId] ?? [], true)) {
                return 'step'.$this->gendered($g, 'son', 'daughter', 'child');
            }
        }
        // B is a blood relative of A's partner => X-in-law
        foreach (array_keys($this->partners[$aId] ?? []) as $p) {
            if ($rel = $this->bloodLabel($p, $bId)) {
                return $this->inLaw($rel, $this->people[$p]);
            }
        }
        // B is the partner of A's blood relative
        foreach (array_keys($this->partners[$bId] ?? []) as $p) {
            if ($rel = $this->bloodLabel($aId, $p)) {
                $map = ['son' => 'daughter-in-law', 'daughter' => 'son-in-law', 'brother' => 'sister-in-law', 'sister' => 'brother-in-law'];
                if (isset($map[$rel])) {
                    return $this->gendered($g, str_replace('daughter', 'son', str_replace('sister', 'brother', $map[$rel])), $map[$rel], 'child-in-law');
                }
                if (in_array($rel, ['child', 'sibling'], true)) {
                    return $rel.'-in-law';
                }
                if (preg_match('/^((?:great-|\d+\w\w great-)*)(uncle|aunt)$/', $rel, $m)) {
                    return $m[1].$this->gendered($g, 'uncle', 'aunt', 'parent’s sibling').' by marriage';
                }

                return "{$rel}’s ".$this->gendered($g, 'husband', 'wife', 'partner');
            }
        }

        return null;
    }

    private function inLaw(string $rel, Person $partner): string
    {
        $direct = ['father', 'mother', 'parent', 'brother', 'sister', 'sibling', 'son', 'daughter', 'child'];
        if (in_array($rel, $direct, true)) {
            return "{$rel}-in-law";
        }
        $partnerWord = $this->gendered($partner->gender, 'husband', 'wife', 'partner');

        return "{$partnerWord}’s {$rel}";
    }

    private function lineal(int $n, string $g, bool $up): string
    {
        $base = $up ? $this->gendered($g, 'father', 'mother', 'parent') : $this->gendered($g, 'son', 'daughter', 'child');

        return $n === 1 ? $base : $this->greatPrefix($n - 2).'grand'.$base;
    }

    /** "", "great-", "great-great-", "3rd great-", ... */
    private function greatPrefix(int $k): string
    {
        if ($k <= 0) {
            return '';
        }
        if ($k <= 2) {
            return str_repeat('great-', $k);
        }
        $suffix = in_array($k % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$k % 10] ?? 'th');

        return "{$k}{$suffix} great-";
    }

    private function cousin(int $degree, int $removed): string
    {
        $label = $this->ordinal($degree).' cousin';
        if ($removed === 1) {
            $label .= ' once removed';
        } elseif ($removed === 2) {
            $label .= ' twice removed';
        } elseif ($removed > 2) {
            $label .= " {$removed} times removed";
        }

        return $label;
    }

    private function ordinal(int $n): string
    {
        return [1 => 'first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth'][$n] ?? $n.'th';
    }

    private function gendered(?string $g, string $m, string $f, string $n): string
    {
        return $g === 'male' ? $m : ($g === 'female' ? $f : $n);
    }

    /**
     * Shortest path over all edges, as steps like ['person' => Person, 'via' => 'child of'].
     */
    public function path(int $from, int $to): array
    {
        $prev = [$from => null];
        $queue = [$from];
        while ($queue) {
            $cur = array_shift($queue);
            if ($cur === $to) {
                break;
            }
            foreach ($this->neighbours($cur) as [$next, $edge]) {
                if (! array_key_exists($next, $prev)) {
                    $prev[$next] = [$cur, $edge];
                    $queue[] = $next;
                }
            }
        }
        if (! array_key_exists($to, $prev)) {
            return [];
        }
        $steps = [];
        for ($cur = $to; $prev[$cur] !== null; $cur = $prev[$cur][0]) {
            $steps[] = ['person' => $this->people[$cur], 'via' => $prev[$cur][1]];
        }
        $steps[] = ['person' => $this->people[$from], 'via' => null];

        return array_reverse($steps);
    }

    private function neighbours(int $id): array
    {
        $out = [];
        foreach ($this->parents[$id] ?? [] as $p => $type) {
            $out[] = [$p, 'parent'];
        }
        foreach ($this->children[$id] ?? [] as $c) {
            $out[] = [$c, 'child'];
        }
        foreach ($this->partners[$id] ?? [] as $p => $_) {
            $out[] = [$p, 'partner'];
        }

        return array_filter($out, fn ($n) => isset($this->people[$n[0]]));
    }
}
