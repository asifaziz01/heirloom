<?php

namespace App\Console\Commands;

use App\Models\Family;
use App\Models\Occasion;
use App\Models\ReminderLog;
use App\Notifications\DatesReminder;
use App\Notifications\OccasionReminder;
use App\Services\Upcoming;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Runs every morning and sends two kinds of reminder email to the members who want them:
 *
 *  - Planned occasions (weddings, reunions, ...): a week before, the day before and on the day.
 *  - Dates from the tree itself (birthdays of the living, wedding anniversaries, death
 *    anniversaries): the day before and on the day, bundled into one email per member.
 *
 * Every batch is recorded in reminder_log first, so running twice in a day never sends twice.
 */
class SendReminders extends Command
{
    protected $signature = 'family:remind {--date= : Pretend today is this date (YYYY-MM-DD)}';

    protected $description = 'Email reminders for upcoming occasions, birthdays and anniversaries';

    public function handle(): int
    {
        $today = $this->option('date') ? CarbonImmutable::parse($this->option('date'))->startOfDay() : CarbonImmutable::today();

        $sent = $this->remindOccasions($today) + $this->remindDates($today);
        $this->info("Sent {$sent} reminder email(s).");

        return self::SUCCESS;
    }

    private function remindOccasions(CarbonImmutable $today): int
    {
        $sent = 0;
        foreach (Occasion::query()->where('send_reminders', true)->current()->with('family')->get() as $occasion) {
            $when = $occasion->nextOccurrence($today);
            if (! $when || $when->lt($today)) {
                continue; // already under way
            }
            $daysUntil = (int) $today->diffInDays($when);
            if (! in_array($daysUntil, Occasion::REMINDER_DAYS, true)) {
                continue;
            }
            $recipients = $occasion->family->reminderRecipients();
            if ($recipients->isEmpty() || ! ReminderLog::claim($occasion->family_id, "occasion:{$occasion->id}:{$when->toDateString()}:{$daysUntil}")) {
                continue;
            }
            Notification::send($recipients, new OccasionReminder($occasion, $daysUntil, $when->toDateString()));
            $sent += $recipients->count();
            $this->line("{$occasion->title}: {$daysUntil} day(s) to go, {$recipients->count()} recipient(s)");
        }

        return $sent;
    }

    private function remindDates(CarbonImmutable $today): int
    {
        $sent = 0;
        foreach (Family::with('members.user')->get() as $family) {
            $cache = [];
            foreach ($family->members as $member) {
                if (! $member->user?->email || ! $member->email_reminders) {
                    continue;
                }
                $member->setRelation('family', $family);
                // What a member may see depends on role, so build the list once per role.
                $key = $member->role.($family->hide_living_from_viewers ? ':h' : '');
                $cache[$key] ??= Upcoming::forFamily($family, $member, 1, $today)
                    ->where('type', '!=', 'occasion')
                    ->groupBy(fn ($i) => $i['when']->isSameDay($today) ? 'today' : 'tomorrow')
                    ->map(fn ($items) => $items->map(fn ($i) => ['type' => $i['type'], 'text' => Upcoming::describe($i), 'url' => Upcoming::url($family, $i)])->values()->all());

                foreach ($cache[$key] as $when => $items) {
                    $on = $when === 'today' ? $today : $today->addDay();
                    if (! ReminderLog::claim($family->id, "dates:{$family->id}:{$member->id}:{$on->toDateString()}:{$when}")) {
                        continue;
                    }
                    $member->user->notify(new DatesReminder($family, $items, $when));
                    $sent++;
                }
            }
        }

        return $sent;
    }
}
