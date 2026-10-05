<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Genealogical dates are often partial ("1940", "March 1942") or approximate.
 * They are stored as "YYYY", "YYYY-MM" or "YYYY-MM-DD" plus a qualifier.
 */
class PartialDate
{
    public const QUALIFIERS = ['exact' => '', 'about' => 'abt.', 'before' => 'bef.', 'after' => 'aft.'];

    public static function normalize(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }
        if (preg_match('/^(\d{4})(?:-(\d{1,2}))?(?:-(\d{1,2}))?$/', $input, $m)) {
            $out = $m[1];
            if (! empty($m[2])) {
                $out .= '-'.str_pad($m[2], 2, '0', STR_PAD_LEFT);
                if (! empty($m[3])) {
                    $out .= '-'.str_pad($m[3], 2, '0', STR_PAD_LEFT);
                }
            }

            return $out;
        }
        // Fall back to natural language parsing ("15 March 1942", "Mar 1942").
        if (preg_match('/^(?:(\d{1,2})\s+)?([A-Za-z]{3,})\.?\s+(\d{4})$/', $input, $m)) {
            $month = date_parse($m[2].' 1 2000')['month'] ?? null;
            if ($month) {
                return $m[3].'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).($m[1] ? '-'.str_pad($m[1], 2, '0', STR_PAD_LEFT) : '');
            }
        }

        return null;
    }

    public static function format(?string $date, ?string $qualifier = 'exact'): string
    {
        if (! $date) {
            return '';
        }
        $parts = explode('-', $date);
        $text = match (count($parts)) {
            3 => ltrim($parts[2], '0').' '.date('M', mktime(0, 0, 0, (int) $parts[1], 1)).' '.$parts[0],
            2 => date('M', mktime(0, 0, 0, (int) $parts[1], 1)).' '.$parts[0],
            default => $parts[0],
        };
        $prefix = self::QUALIFIERS[$qualifier ?? 'exact'] ?? '';

        return trim($prefix.' '.$text);
    }

    public static function year(?string $date): ?int
    {
        return $date ? (int) substr($date, 0, 4) : null;
    }

    /** Sortable key; missing month/day sort to the start of the period. */
    public static function sortKey(?string $date): string
    {
        if (! $date) {
            return '9999-99-99';
        }
        $parts = explode('-', $date) + [1 => '00', 2 => '00'];

        return sprintf('%s-%s-%s', $parts[0], $parts[1], $parts[2]);
    }

    public static function isComplete(?string $date): bool
    {
        return $date !== null && strlen($date) === 10;
    }

    public static function toCarbon(?string $date): ?CarbonImmutable
    {
        return self::isComplete($date) ? CarbonImmutable::parse($date) : null;
    }

    /** Approximate age in whole years between two partial dates (or until today). */
    public static function age(?string $from, ?string $to = null): ?int
    {
        if (! $from) {
            return null;
        }
        $to ??= now()->toDateString();
        $a = self::toCarbon($from);
        $b = self::toCarbon($to);
        if ($a && $b) {
            return (int) $a->diffInYears($b);
        }

        return self::year($to) - self::year($from);
    }
}
