<?php

namespace App\Services\Gedcom;

use App\Models\Family;
use App\Models\ParentChild;
use App\Models\Partnership;
use App\Models\Person;
use Illuminate\Support\Collection;

/**
 * Serialises a family's graph to GEDCOM 5.5.1 (UTF-8, lineage-linked).
 * Each partnership becomes a FAM; parent links without a partnership are grouped
 * into synthetic FAMs per set of parents so no link is lost.
 */
class GedcomExporter
{
    public const LANGUAGES = ['en' => 'English', 'hi' => 'Hindi', 'ur' => 'Urdu', 'ar' => 'Arabic', 'bn' => 'Bengali',
        'pa' => 'Panjabi', 'gu' => 'Gujarati', 'ta' => 'Tamil', 'te' => 'Telugu', 'mr' => 'Marathi', 'fa' => 'Persian',
        'fr' => 'French', 'es' => 'Spanish', 'de' => 'German', 'zh' => 'Chinese'];

    public const NAME_TYPES = ['birth' => 'birth', 'married' => 'married', 'religious' => 'religious', 'alternate' => 'aka',
        'nickname' => 'aka', 'transliteration' => 'aka'];

    public const PEDIGREE = ['biological' => 'birth', 'adoptive' => 'adopted', 'foster' => 'foster'];

    public const RELATIONSHIP = ['biological' => 'Natural', 'adoptive' => 'Adopted', 'step' => 'Step', 'foster' => 'Foster',
        'guardian' => 'Guardian', 'unknown' => 'Unknown'];

    private const MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

    private const QUALIFIERS = ['about' => 'ABT ', 'before' => 'BEF ', 'after' => 'AFT '];

    /** @var string[] */
    private array $lines = [];

    public function __construct(private Family $family) {}

    public function export(): string
    {
        $this->lines = [];
        $people = Person::where('family_id', $this->family->id)->with(['names', 'birthPlace', 'deathPlace'])->orderBy('id')->get()->keyBy('id');
        $partnerships = Partnership::where('family_id', $this->family->id)->with('place')->orderBy('id')->get();
        $links = ParentChild::where('family_id', $this->family->id)->orderBy('id')->get()
            ->filter(fn ($l) => $people->has($l->parent_id) && $people->has($l->child_id));

        $fams = $this->buildFamilies($people, $partnerships->filter(fn ($p) => $people->has($p->partner1_id)), $links);

        // Per-person FAMS / FAMC pointers.
        $famsOf = [];
        $famcOf = [];
        foreach ($fams as $xref => $fam) {
            foreach (array_filter([$fam['husb'], $fam['wife']]) as $pid) {
                $famsOf[$pid][] = $xref;
            }
            foreach ($fam['children'] as $cid => $types) {
                $famcOf[$cid][] = [$xref, array_values(array_unique($types))];
            }
        }

        $this->header();
        foreach ($people as $person) {
            $this->individual($person, $famsOf[$person->id] ?? [], $famcOf[$person->id] ?? []);
        }
        foreach ($fams as $xref => $fam) {
            $this->familyRecord($xref, $fam);
        }
        $this->line(0, 'TRLR');

        return implode("\r\n", $this->lines)."\r\n";
    }

    /** @return array<string, array{husb: ?int, wife: ?int, partnership: ?Partnership, children: array<int, array<int, string>>}> */
    private function buildFamilies(Collection $people, Collection $partnerships, Collection $links): array
    {
        $fams = [];
        $byPartnership = [];
        foreach ($partnerships as $p) {
            [$husb, $wife] = $this->assignRoles($people[$p->partner1_id], $p->partner2_id ? $people[$p->partner2_id] ?? null : null);
            $xref = 'F'.(count($fams) + 1);
            $fams[$xref] = ['husb' => $husb?->id, 'wife' => $wife?->id, 'partnership' => $p, 'children' => []];
            $byPartnership[$p->id] = $xref;
        }

        // children: childId => [parentId => link type]
        $loose = [];
        foreach ($links as $link) {
            $xref = $link->partnership_id ? ($byPartnership[$link->partnership_id] ?? null) : null;
            if ($xref && in_array($link->parent_id, [$fams[$xref]['husb'], $fams[$xref]['wife']], true)) {
                $fams[$xref]['children'][$link->child_id][$link->parent_id] = $link->type;
            } else {
                $loose[$link->child_id][$link->parent_id] = $link->type;
            }
        }

        // CHIL implies every parent in the FAM, so a child linked to only one of two partners moves to a synthetic FAM.
        foreach ($fams as $xref => $fam) {
            $partners = count(array_filter([$fam['husb'], $fam['wife']]));
            foreach ($fam['children'] as $childId => $parents) {
                if (count($parents) < $partners) {
                    unset($fams[$xref]['children'][$childId]);
                    $loose[$childId] = ($loose[$childId] ?? []) + $parents;
                }
            }
        }

        $synthetic = [];
        foreach ($loose as $childId => $parents) {
            ksort($parents);
            foreach (array_chunk($parents, 2, true) as $pair) { // a FAM holds at most two parents
                $key = implode('-', array_keys($pair));
                if (! isset($synthetic[$key])) {
                    $ids = array_keys($pair);
                    [$husb, $wife] = $this->assignRoles($people[$ids[0]], isset($ids[1]) ? $people[$ids[1]] : null);
                    $xref = 'F'.(count($fams) + 1);
                    $fams[$xref] = ['husb' => $husb?->id, 'wife' => $wife?->id, 'partnership' => null, 'children' => []];
                    $synthetic[$key] = $xref;
                }
                $fams[$synthetic[$key]]['children'][$childId] = $pair;
            }
        }

        return $fams;
    }

    /** @return array{0: ?Person, 1: ?Person} [HUSB, WIFE] */
    private function assignRoles(Person $a, ?Person $b): array
    {
        if ($b === null) {
            return $a->gender === 'female' ? [null, $a] : [$a, null];
        }
        if ($a->gender === 'female' && $b->gender === 'male') {
            return [$b, $a];
        }

        return [$a, $b];
    }

    private function header(): void
    {
        $this->line(0, 'HEAD');
        $this->line(1, 'SOUR', 'HEIRLOOM');
        $this->line(2, 'NAME', 'Heirloom');
        $this->line(1, 'DATE', strtoupper(now()->format('j M Y')));
        $this->line(1, 'SUBM', '@SUB1@');
        $this->line(1, 'FILE', $this->family->slug.'.ged');
        $this->line(1, 'GEDC');
        $this->line(2, 'VERS', '5.5.1');
        $this->line(2, 'FORM', 'LINEAGE-LINKED');
        $this->line(1, 'CHAR', 'UTF-8');
        $this->line(0, '@SUB1@ SUBM');
        $this->line(1, 'NAME', $this->family->name);
    }

    private function individual(Person $p, array $fams, array $famc): void
    {
        $this->line(0, "@I{$p->id}@ INDI");
        $this->line(1, 'NAME', trim($p->given_name.' /'.$p->surname.'/'));
        $this->line(2, 'GIVN', $p->given_name);
        $this->line(2, 'SURN', $p->surname);
        $this->line(2, 'NICK', $p->nickname);
        foreach ($p->names as $name) {
            $this->line(1, 'NAME', $name->full_name);
            $this->line(2, 'TYPE', self::NAME_TYPES[$name->type] ?? 'aka');
            if ($name->language && $name->language !== 'en' && isset(self::LANGUAGES[$name->language])) {
                $this->line(2, 'LANG', self::LANGUAGES[$name->language]);
            }
        }
        $this->line(1, 'SEX', ['male' => 'M', 'female' => 'F'][$p->gender] ?? 'U');
        $this->event('BIRT', $p->birth_date, $p->birth_qualifier, $p->birthPlace?->name);
        if ($p->death_date || $p->death_place_id) {
            $this->event('DEAT', $p->death_date, $p->death_qualifier, $p->deathPlace?->name);
        } elseif (! $p->is_living) {
            $this->line(1, 'DEAT', 'Y');
        }
        $this->line(1, 'OCCU', $p->occupation);
        $this->line(1, 'EDUC', $p->education);
        $this->line(1, 'RELI', $p->religion);
        $this->text(1, 'NOTE', $p->biography);
        foreach ($famc as [$xref, $types]) {
            $this->line(1, 'FAMC', "@{$xref}@");
            // PEDI only when both parents share a type 5.5.1 can express; _FREL/_MREL on CHIL carry the rest.
            if (count($types) === 1) {
                $this->line(2, 'PEDI', self::PEDIGREE[$types[0]] ?? null);
            }
        }
        foreach (array_unique($fams) as $xref) {
            $this->line(1, 'FAMS', "@{$xref}@");
        }
    }

    private function familyRecord(string $xref, array $fam): void
    {
        $this->line(0, "@{$xref}@ FAM");
        $this->line(1, 'HUSB', $fam['husb'] ? "@I{$fam['husb']}@" : null);
        $this->line(1, 'WIFE', $fam['wife'] ? "@I{$fam['wife']}@" : null);
        /** @var Partnership|null $p */
        if ($p = $fam['partnership']) {
            if (in_array($p->type, ['marriage', 'civil'], true)) {
                $this->event('MARR', $p->start_date, 'exact', $p->place?->name, force: true);
                if ($p->type === 'civil') {
                    $this->line(2, 'TYPE', 'Civil union');
                }
            } elseif ($p->type === 'engagement') {
                $this->event('ENGA', $p->start_date, 'exact', $p->place?->name, force: true);
            }
            if ($p->status === 'divorced') {
                $this->event('DIV', $p->end_date, 'exact', null, force: true);
            } elseif ($p->status === 'annulled') {
                $this->event('ANUL', $p->end_date, 'exact', null, force: true);
            } else {
                $this->line(1, '_END', self::date($p->end_date));
            }
            // Heirloom extensions so details 5.5.1 cannot express survive a round trip.
            if (in_array($p->type, ['partnership', 'unknown'], true)) {
                $this->line(1, '_TYPE', $p->type);
                $this->line(1, '_START', self::date($p->start_date));
            }
            if (! in_array($p->status, ['current', 'divorced', 'annulled'], true)) {
                $this->line(1, '_STAT', $p->status);
            }
        } else {
            $this->line(1, '_SYNTH', 'Y'); // parent links without a partnership; importers should not create a couple
        }
        foreach ($fam['children'] as $cid => $types) {
            $this->line(1, 'CHIL', "@I{$cid}@");
            // De facto per-parent relationship tags (Family Tree Maker, RootsMagic, Gramps read these).
            if ($fam['husb'] && isset($types[$fam['husb']])) {
                $this->line(2, '_FREL', self::RELATIONSHIP[$types[$fam['husb']]] ?? 'Unknown');
            }
            if ($fam['wife'] && isset($types[$fam['wife']])) {
                $this->line(2, '_MREL', self::RELATIONSHIP[$types[$fam['wife']]] ?? 'Unknown');
            }
        }
    }

    private function event(string $tag, ?string $date, ?string $qualifier, ?string $place, bool $force = false): void
    {
        $gedDate = self::date($date, $qualifier);
        if (! $gedDate && ! $place) {
            if ($force) {
                $this->line(1, $tag, 'Y');
            }

            return;
        }
        $this->line(1, $tag);
        $this->line(2, 'DATE', $gedDate);
        $this->line(2, 'PLAC', $place);
    }

    /** "1942-03-15" + about → "ABT 15 MAR 1942". */
    public static function date(?string $date, ?string $qualifier = 'exact'): ?string
    {
        if (! $date || ! preg_match('/^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?$/', $date, $m)) {
            return null;
        }
        $out = $m[1];
        if (! empty($m[2]) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            $out = self::MONTHS[(int) $m[2] - 1].' '.$out;
            if (! empty($m[3]) && (int) $m[3] > 0) {
                $out = (int) $m[3].' '.$out;
            }
        }

        return (self::QUALIFIERS[$qualifier ?? 'exact'] ?? '').$out;
    }

    /** Free text split into CONT (newlines) and CONC (lines longer than the GEDCOM limit). */
    private function text(int $level, string $tag, ?string $value): void
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        if ($value === '') {
            return;
        }
        foreach (explode("\n", $value) as $i => $para) {
            $chunks = $para === '' ? [''] : $this->chunk($para, 200);
            foreach ($chunks as $j => $chunk) {
                $tagHere = match (true) {
                    $i === 0 && $j === 0 => $tag,
                    $j === 0 => 'CONT',
                    default => 'CONC',
                };
                $prefix = ($i === 0 && $j === 0 ? $level : $level + 1).' '.$tagHere;
                $this->lines[] = $chunk === '' ? $prefix : $prefix.' '.$chunk;
            }
        }
    }

    /** Split on character boundaries, avoiding a split right before/after a space (CONC drops edge whitespace in many readers). */
    private function chunk(string $text, int $size): array
    {
        $chunks = [];
        while (mb_strlen($text) > $size) {
            $cut = $size;
            while ($cut > 1 && (mb_substr($text, $cut - 1, 1) === ' ' || mb_substr($text, $cut, 1) === ' ')) {
                $cut--;
            }
            $chunks[] = mb_substr($text, 0, $cut);
            $text = mb_substr($text, $cut);
        }
        $chunks[] = $text;

        return $chunks;
    }

    private function line(int $level, string $tag, ?string $value = ''): void
    {
        if ($value === null) {
            return;
        }
        $value = trim(preg_replace('/[\r\n]+/', ' ', $value));
        if ($value === '' && func_num_args() === 3) {
            return; // optional value that is empty
        }
        $line = $value === '' ? "{$level} {$tag}" : "{$level} {$tag} {$value}";
        if (mb_strlen($line) > 255) {
            $this->text($level, $tag, $value);

            return;
        }
        $this->lines[] = $line;
    }
}
