<?php

namespace App\Services\Gedcom;

use App\Models\ActivityLog;
use App\Models\Family;
use App\Models\ParentChild;
use App\Models\Partnership;
use App\Models\Person;
use App\Models\PersonName;
use App\Services\PlaceResolver;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports a GEDCOM 5.5 / 5.5.1 file (UTF-8) into a family as new people, partnerships and parent links.
 * Existing records are never touched; every INDI becomes a new Person with gedcom_id = its xref.
 */
class GedcomImporter
{
    private const MONTHS = ['JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12];

    private const PEDIGREE = ['birth' => 'biological', 'adopted' => 'adoptive', 'foster' => 'foster'];

    private const RELATIONSHIP = ['natural' => 'biological', 'birth' => 'biological', 'biological' => 'biological',
        'adopted' => 'adoptive', 'adoptive' => 'adoptive', 'step' => 'step', 'stepchild' => 'step', 'foster' => 'foster',
        'guardian' => 'guardian', 'ward' => 'guardian'];

    private const NAME_TYPES = ['aka' => 'alternate', 'birth' => 'birth', 'maiden' => 'birth', 'married' => 'married',
        'religious' => 'religious', 'immigrant' => 'alternate', 'nickname' => 'nickname'];

    /** @var array<string, array> level-0 records keyed by xref */
    private array $records = [];

    private array $warnings = [];

    public function __construct(private Family $family, private ?int $userId = null) {}

    /** @return array{people: int, partnerships: int, links: int, warnings: string[]} */
    public function import(string $contents): array
    {
        $this->warnings = [];
        $this->records = $this->parse($this->decode($contents));
        $individuals = array_filter($this->records, fn ($r) => $r['tag'] === 'INDI');
        $families = array_filter($this->records, fn ($r) => $r['tag'] === 'FAM');

        if (! $individuals) {
            throw new RuntimeException('No individuals (INDI records) were found in this file.');
        }
        if ($reason = $this->family->limitReached('people', count($individuals))) {
            throw new RuntimeException($reason);
        }

        $stats = DB::transaction(fn () => ActivityLog::quietly(function () use ($individuals, $families) {
            $people = [];
            foreach ($individuals as $xref => $indi) {
                $people[$xref] = $this->createPerson($xref, $indi);
            }
            [$partnerships, $links] = $this->createFamilies($families, $individuals, $people);

            return ['people' => count($people), 'partnerships' => $partnerships, 'links' => $links];
        }));

        ActivityLog::create([
            'family_id' => $this->family->id,
            'user_id' => $this->userId,
            'action' => 'imported',
            'subject_type' => 'Family',
            'subject_id' => $this->family->id,
            'description' => "Imported GEDCOM: {$stats['people']} people",
            'changes' => $stats,
        ]);

        return $stats + ['warnings' => $this->warnings];
    }

    private function decode(string $contents): string
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        if (preg_match('/^\s*1\s+CHAR\s+(\S+)/mi', $contents, $m) && ! in_array(strtoupper($m[1]), ['UTF-8', 'UTF8', 'ASCII'], true)) {
            $this->warnings[] = "File declares CHAR {$m[1]}; only UTF-8 is supported, so some characters may be wrong.";
        }
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $this->warnings[] = 'File is not valid UTF-8; it was read as Windows-1252.';
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        return $contents;
    }

    /**
     * Parse lines into a tree of level-0 records keyed by xref (records without an xref get a synthetic key).
     * Each node: ['tag' => string, 'value' => string, 'children' => node[]]. CONC/CONT are folded into the value.
     */
    private function parse(string $contents): array
    {
        $records = [];
        $stack = [];
        $last = null;
        foreach (preg_split('/\r\n|\r|\n/', $contents) as $n => $raw) {
            if (trim($raw) === '') {
                continue;
            }
            if (! preg_match('/^\s*(\d{1,2})\s+(?:(@[^@\s]+@)\s+)?([A-Za-z0-9_]+)(?: (.*))?$/', $raw, $m)) {
                // Some exporters wrap long values without CONT; treat stray lines as continuations.
                if ($last !== null) {
                    $last['value'] .= "\n".trim($raw);
                } else {
                    $this->warnings[] = 'Skipped unreadable line '.($n + 1).'.';
                }

                continue;
            }
            $level = (int) $m[1];
            $tag = strtoupper($m[3]);
            $value = $m[4] ?? '';

            if (($tag === 'CONC' || $tag === 'CONT') && $level > 0 && isset($stack[$level - 1])) {
                $stack[$level - 1]['value'] .= ($tag === 'CONT' ? "\n" : '').$value;

                continue;
            }

            $node = ['tag' => $tag, 'value' => $value, 'children' => []];
            if ($level === 0) {
                $stack = [];
                $key = $m[2] ?: '#'.$n;
                $records[$key] = $node;
                $stack[0] = &$records[$key];
            } elseif (isset($stack[$level - 1])) {
                $parent = &$stack[$level - 1];
                $parent['children'][] = $node;
                $stack[$level] = &$parent['children'][array_key_last($parent['children'])];
                unset($parent);
            } else {
                $this->warnings[] = 'Skipped line '.($n + 1).' with an unexpected level.';

                continue;
            }
            foreach (array_keys($stack) as $l) {
                if ($l > $level) {
                    unset($stack[$l]);
                }
            }
            $last = &$stack[$level];
        }
        unset($stack, $last);

        return $records;
    }

    private function createPerson(string $xref, array $indi): Person
    {
        $names = $this->all($indi, 'NAME');
        $primary = $names[0] ?? ['value' => '', 'children' => []];
        [$given, $surname] = $this->splitName($primary['value']);
        $given = trim($this->sub($primary, 'GIVN') ?? $given) ?: 'Unknown';
        $surname = trim($this->sub($primary, 'SURN') ?? $surname) ?: null;

        [$birthDate, $birthQ] = $this->date($this->sub($this->first($indi, 'BIRT'), 'DATE'));
        [$deathDate, $deathQ] = $this->date($this->sub($this->first($indi, 'DEAT'), 'DATE'));
        $dead = $this->first($indi, 'DEAT') || $this->first($indi, 'BURI') || $this->first($indi, 'CREM')
            || ($birthDate && (int) substr($birthDate, 0, 4) < now()->year - 110);

        $person = Person::create([
            'family_id' => $this->family->id,
            'given_name' => mb_substr($given, 0, 255),
            'surname' => $surname ? mb_substr($surname, 0, 255) : null,
            'nickname' => $this->sub($primary, 'NICK') ?: $this->sub($indi, 'NICK'),
            'gender' => ['M' => 'male', 'F' => 'female', 'X' => 'other'][strtoupper(substr((string) $this->sub($indi, 'SEX'), 0, 1))] ?? 'unknown',
            'birth_date' => $birthDate,
            'birth_qualifier' => $birthQ,
            'birth_place_id' => PlaceResolver::resolve($this->family, $this->sub($this->first($indi, 'BIRT'), 'PLAC')),
            'death_date' => $deathDate,
            'death_qualifier' => $deathQ,
            'death_place_id' => PlaceResolver::resolve($this->family, $this->sub($this->first($indi, 'DEAT'), 'PLAC')),
            'is_living' => ! $dead,
            'occupation' => $this->short($this->sub($indi, 'OCCU')),
            'education' => $this->short($this->sub($indi, 'EDUC')),
            'religion' => $this->short($this->sub($indi, 'RELI')),
            'biography' => $this->notes($indi),
            'created_by' => $this->userId,
            'gedcom_id' => $xref,
        ]);

        foreach (array_slice($names, 1) as $name) {
            $full = trim(preg_replace('/\s+/', ' ', str_replace('/', ' ', $name['value'])));
            if ($full === '') {
                continue;
            }
            $lang = strtolower((string) $this->sub($name, 'LANG'));
            PersonName::create([
                'person_id' => $person->id,
                'type' => self::NAME_TYPES[strtolower((string) $this->sub($name, 'TYPE'))] ?? 'alternate',
                'language' => array_search($lang, array_map('strtolower', GedcomExporter::LANGUAGES), true) ?: ($lang ? 'other' : 'en'),
                'full_name' => mb_substr($full, 0, 255),
            ]);
        }

        return $person;
    }

    /** @return array{0: int, 1: int} [partnerships created, links created] */
    private function createFamilies(array $families, array $individuals, array $people): array
    {
        $partnershipCount = 0;
        $seen = [];

        // Children per FAM: CHIL lines plus any INDI FAMC pointers the FAM forgot to list.
        $childrenOf = [];
        foreach ($families as $famXref => $fam) {
            foreach ($this->all($fam, 'CHIL') as $chil) {
                $childrenOf[$famXref][$chil['value']] = $chil;
            }
        }
        foreach ($individuals as $xref => $indi) {
            foreach ($this->all($indi, 'FAMC') as $famc) {
                if (isset($families[$famc['value']])) {
                    $childrenOf[$famc['value']][$xref] ??= ['value' => $xref, 'children' => []];
                }
            }
        }

        foreach ($families as $famXref => $fam) {
            $husb = $people[$this->sub($fam, 'HUSB')] ?? null;
            $wife = $people[$this->sub($fam, 'WIFE')] ?? null;
            foreach (['HUSB', 'WIFE'] as $role) {
                if (($ref = $this->sub($fam, $role)) && ! isset($people[$ref])) {
                    $this->warnings[] = "Family {$famXref} refers to unknown person {$ref}.";
                }
            }
            if (! $husb && ! $wife) {
                if (! empty($childrenOf[$famXref])) {
                    $this->warnings[] = "Family {$famXref} has children but no parents; skipped.";
                }

                continue;
            }

            $partnership = null;
            if (! $this->first($fam, '_SYNTH')) {
                $partnership = $this->createPartnership($fam, $husb ?? $wife, $husb ? $wife : null);
                $partnershipCount++;
            }

            foreach ($childrenOf[$famXref] ?? [] as $childXref => $chil) {
                $child = $people[$childXref] ?? null;
                if (! $child) {
                    $this->warnings[] = "Family {$famXref} lists unknown child {$childXref}.";

                    continue;
                }
                $pedigree = $this->pedigree($individuals[$childXref] ?? [], $famXref);
                foreach (['_FREL' => $husb, '_MREL' => $wife] as $relTag => $parent) {
                    if (! $parent || $parent->id === $child->id || isset($seen[$parent->id.'-'.$child->id])) {
                        continue;
                    }
                    $rel = strtolower((string) $this->sub($chil, $relTag));
                    ParentChild::create([
                        'family_id' => $this->family->id,
                        'parent_id' => $parent->id,
                        'child_id' => $child->id,
                        'partnership_id' => $partnership?->id,
                        'type' => self::RELATIONSHIP[$rel] ?? ($rel ? 'unknown' : $pedigree),
                    ]);
                    $seen[$parent->id.'-'.$child->id] = true;
                }
            }
        }

        return [$partnershipCount, count($seen)];
    }

    private function createPartnership(array $fam, Person $partner1, ?Person $partner2): Partnership
    {
        $marr = $this->first($fam, 'MARR');
        $enga = $this->first($fam, 'ENGA');
        $div = $this->first($fam, 'DIV');
        $anul = $this->first($fam, 'ANUL');
        $event = $marr ?? $enga;
        $custom = strtolower((string) $this->sub($fam, '_TYPE'));

        $type = match (true) {
            $marr !== null => str_contains(strtolower((string) $this->sub($marr, 'TYPE')), 'civil') ? 'civil' : 'marriage',
            $enga !== null => 'engagement',
            isset(Partnership::TYPES[$custom]) => $custom,
            default => $partner2 ? 'partnership' : 'unknown',
        };
        $status = strtolower((string) $this->sub($fam, '_STAT'));
        $status = match (true) {
            $div !== null => 'divorced',
            $anul !== null => 'annulled',
            isset(Partnership::STATUSES[$status]) => $status,
            default => 'current',
        };
        $end = $this->sub($div ?? $anul, 'DATE') ?? $this->sub($fam, '_END');

        return Partnership::create([
            'family_id' => $this->family->id,
            'partner1_id' => $partner1->id,
            'partner2_id' => $partner2?->id,
            'type' => $type,
            'status' => $status,
            'start_date' => $this->date($this->sub($event, 'DATE') ?? $this->sub($fam, '_START'))[0],
            'end_date' => $this->date($end)[0],
            'place_id' => PlaceResolver::resolve($this->family, $this->sub($event, 'PLAC')),
        ]);
    }

    /** Link type from the child's "1 FAMC @F1@ / 2 PEDI adopted" sub-record. */
    private function pedigree(array $indi, string $famXref): string
    {
        foreach ($this->all($indi, 'FAMC') as $famc) {
            if ($famc['value'] === $famXref && ($pedi = $this->sub($famc, 'PEDI'))) {
                return self::PEDIGREE[strtolower($pedi)] ?? 'unknown';
            }
        }

        return 'biological';
    }

    /**
     * GEDCOM date → [partial ISO date, qualifier].
     * "ABT 15 MAR 1942" → ["1942-03-15", "about"]; "BET 1940 AND 1945" → ["1940", "about"].
     */
    public static function date(?string $value): array
    {
        $value = strtoupper(trim(preg_replace('/\(.*?\)/', '', (string) $value)));
        if ($value === '') {
            return [null, 'exact'];
        }
        $qualifier = 'exact';
        if (preg_match('/^(ABT|EST|CAL|ABOUT|CIRCA|C\.?)\s+(.*)$/', $value, $m)) {
            [$qualifier, $value] = ['about', $m[2]];
        } elseif (preg_match('/^(BEF|BEFORE|TO)\s+(.*)$/', $value, $m)) {
            [$qualifier, $value] = ['before', $m[2]];
        } elseif (preg_match('/^(AFT|AFTER)\s+(.*)$/', $value, $m)) {
            [$qualifier, $value] = ['after', $m[2]];
        } elseif (preg_match('/^(?:BET|FROM)\s+(.*?)(?:\s+(?:AND|TO)\s+.*)?$/', $value, $m)) {
            [$qualifier, $value] = ['about', $m[1]];
        } elseif (preg_match('/^INT\s+(.*)$/', $value, $m)) {
            $value = $m[1];
        }
        $value = preg_replace('/^@#D[A-Z ]+@\s*/', '', $value); // calendar escapes, e.g. @#DGREGORIAN@

        if (! preg_match('/^(?:(\d{1,2})\s+)?(?:([A-Z]{3})\s+)?(\d{3,4})(?:\/\d{1,2})?$/', trim($value), $m)) {
            return [null, 'exact'];
        }
        $year = str_pad($m[3], 4, '0', STR_PAD_LEFT);
        $month = $m[2] !== '' ? (self::MONTHS[$m[2]] ?? null) : null;
        if (! $month) {
            return [$year, $qualifier];
        }
        $out = $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $day = (int) $m[1];
        if ($day >= 1 && $day <= 31 && checkdate($month, $day, (int) $year)) {
            $out .= '-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        }

        return [$out, $qualifier];
    }

    /** "John Paul /Smith/ Jr" → ["John Paul", "Smith"]. */
    private function splitName(string $value): array
    {
        if (preg_match('/^(.*?)\/(.*?)\/(.*)$/', $value, $m)) {
            return [trim(preg_replace('/\s+/', ' ', $m[1].' '.$m[3])), trim($m[2])];
        }

        return [trim($value), ''];
    }

    private function notes(array $indi): ?string
    {
        $texts = [];
        foreach ($this->all($indi, 'NOTE') as $note) {
            $text = $note['value'];
            if (preg_match('/^@[^@]+@$/', trim($text))) {
                $text = $this->records[trim($text)]['value'] ?? '';
            }
            if (trim($text) !== '') {
                $texts[] = trim($text);
            }
        }

        return $texts ? implode("\n\n", $texts) : null;
    }

    private function short(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr(str_replace("\n", ' ', $value), 0, 255);
    }

    private function first(?array $node, string $tag): ?array
    {
        foreach ($node['children'] ?? [] as $child) {
            if ($child['tag'] === $tag) {
                return $child;
            }
        }

        return null;
    }

    private function all(?array $node, string $tag): array
    {
        return array_values(array_filter($node['children'] ?? [], fn ($c) => $c['tag'] === $tag));
    }

    /** Value of the first child with the given tag, or null when absent/empty. */
    private function sub(?array $node, string $tag): ?string
    {
        $value = trim((string) ($this->first($node, $tag)['value'] ?? ''));

        return $value === '' ? null : $value;
    }
}
