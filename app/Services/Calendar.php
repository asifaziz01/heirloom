<?php

namespace App\Services;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Occasion;
use App\Support\PartialDate;
use Carbon\CarbonImmutable;

/**
 * Builds an iCalendar (.ics) feed of a family's occasions, birthdays and anniversaries,
 * so phones and calendar apps (Google Calendar, Apple Calendar, Outlook) show them and
 * raise their own reminders. No package: the format is a handful of text lines.
 */
class Calendar
{
    public static function forFamily(Family $family, ?FamilyMember $member): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.self::text(config('app.name')).'//Family calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::text($family->name),
            'X-PUBLISHED-TTL:PT12H',
        ];
        $stamp = CarbonImmutable::now('UTC')->format('Ymd\THis\Z');
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'family';

        foreach ($family->occasions()->current()->with('place')->get() as $o) {
            $lines = array_merge($lines, self::occasion($o, $family, $stamp, $host));
        }

        $people = $family->persons()->visibleTo($member)->get();
        foreach ($people as $p) {
            if ($p->is_living && PartialDate::isComplete($p->birth_date)) {
                $lines = array_merge($lines, self::yearly("person-{$p->id}-birthday@{$host}", $p->birth_date, "🎂 {$p->full_name}’s birthday", 'Born '.PartialDate::format($p->birth_date), route('people.show', [$family, $p]), $stamp));
            }
            if (! $p->is_living && PartialDate::isComplete($p->death_date)) {
                $lines = array_merge($lines, self::yearly("person-{$p->id}-remembrance@{$host}", $p->death_date, "🕯️ Remembering {$p->full_name}", 'Passed away '.PartialDate::format($p->death_date), route('people.show', [$family, $p]), $stamp));
            }
        }
        $ids = $people->pluck('id');
        foreach ($family->partnerships()->where('status', 'current')->whereNotNull('start_date')->with(['partner1', 'partner2'])->get() as $u) {
            if ($u->partner1?->is_living && $u->partner2?->is_living && $ids->contains($u->partner1_id) && PartialDate::isComplete($u->start_date)) {
                $names = collect([$u->partner1->given_name, $u->partner2->given_name])->join(' & ');
                $lines = array_merge($lines, self::yearly("partnership-{$u->id}@{$host}", $u->start_date, "💍 {$names} – anniversary", 'Married '.PartialDate::format($u->start_date), route('partnerships.edit', [$family, $u]), $stamp));
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines))."\r\n";
    }

    /** A single occasion, for an "Add to my calendar" button. */
    public static function forOccasion(Occasion $o): string
    {
        $o->loadMissing(['family', 'place']);
        $lines = array_merge(
            ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//'.self::text(config('app.name')).'//Family calendar//EN', 'METHOD:PUBLISH'],
            self::occasion($o, $o->family, CarbonImmutable::now('UTC')->format('Ymd\THis\Z'), parse_url(config('app.url'), PHP_URL_HOST) ?: 'family'),
            ['END:VCALENDAR'],
        );

        return implode("\r\n", array_map([self::class, 'fold'], $lines))."\r\n";
    }

    private static function occasion(Occasion $o, Family $family, string $stamp, string $host): array
    {
        $start = $o->date;
        $end = $o->end_date ?? $o->date;
        $out = [
            'BEGIN:VEVENT',
            "UID:occasion-{$o->id}@{$host}",
            "DTSTAMP:{$stamp}",
            'SUMMARY:'.self::text($o->icon().' '.$o->title),
        ];
        if ($o->time && $start->equalTo($end)) {
            $at = $start->setTimeFromTimeString($o->time);
            $out[] = 'DTSTART:'.$at->format('Ymd\THis');
            $out[] = 'DTEND:'.$at->addHours(3)->format('Ymd\THis');
        } else {
            $out[] = 'DTSTART;VALUE=DATE:'.$start->format('Ymd');
            $out[] = 'DTEND;VALUE=DATE:'.$end->addDay()->format('Ymd'); // DTEND is exclusive
        }
        if ($o->repeats_yearly) {
            $out[] = 'RRULE:FREQ=YEARLY';
        }
        if ($where = $o->whereLabel()) {
            $out[] = 'LOCATION:'.self::text($where);
        }
        $description = trim(($o->description ? $o->description."\n\n" : '').route('occasions.show', [$family, $o]));
        $out[] = 'DESCRIPTION:'.self::text($description);
        $out[] = 'URL:'.route('occasions.show', [$family, $o]);
        $out[] = 'BEGIN:VALARM';
        $out[] = 'TRIGGER:-P1D';
        $out[] = 'ACTION:DISPLAY';
        $out[] = 'DESCRIPTION:'.self::text($o->title.' is tomorrow');
        $out[] = 'END:VALARM';
        $out[] = 'END:VEVENT';

        return $out;
    }

    private static function yearly(string $uid, string $date, string $summary, string $description, string $url, string $stamp): array
    {
        $d = CarbonImmutable::parse($date);

        return [
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$stamp}",
            'DTSTART;VALUE=DATE:'.$d->format('Ymd'),
            'DTEND;VALUE=DATE:'.$d->addDay()->format('Ymd'),
            'RRULE:FREQ=YEARLY',
            'SUMMARY:'.self::text($summary),
            'DESCRIPTION:'.self::text($description."\n".$url),
            "URL:{$url}",
            'END:VEVENT',
        ];
    }

    /** Escape per RFC 5545: backslashes, commas, semicolons and newlines. */
    private static function text(string $value): string
    {
        return str_replace(['\\', ',', ';', "\r\n", "\n"], ['\\\\', '\\,', '\\;', '\\n', '\\n'], $value);
    }

    /** Lines longer than 75 octets continue on the next line after a single space. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        while (strlen($line) > 75) {
            $cut = 75;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) { // never split a multibyte character
                $cut--;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $out.$line;
    }
}
