<?php

namespace App\Console\Commands;

use App\Models\Family;
use App\Notifications\WeeklyDigest;
use App\Services\Upcoming;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Monday morning email: what is coming up in the next seven days. Built per member,
 * because what each person may see differs by role (private profiles, living people
 * hidden from viewers).
 */
class SendWeeklyDigest extends Command
{
    protected $signature = 'family:digest {--date= : Pretend today is this date (YYYY-MM-DD)} {--days=7}';

    protected $description = 'Email each family a digest of the birthdays, anniversaries and occasions in the coming week';

    public function handle(): int
    {
        $today = $this->option('date') ? CarbonImmutable::parse($this->option('date'))->startOfDay() : CarbonImmutable::today();
        $days = (int) $this->option('days');
        $sent = 0;

        foreach (Family::with('members.user')->get() as $family) {
            $cache = [];
            foreach ($family->members as $member) {
                $user = $member->user;
                if (! $user?->email || ! $member->email_reminders) {
                    continue;
                }
                $member->setRelation('family', $family);
                $key = $member->role.($family->hide_living_from_viewers ? ':h' : '');
                $cache[$key] ??= Upcoming::forFamily($family, $member, $days, $today)->map(fn ($i) => [
                    'date' => $i['when']->format('D j M'),
                    'text' => Upcoming::describe($i),
                    'url' => Upcoming::url($family, $i),
                ])->all();
                if ($cache[$key] === []) {
                    continue;
                }
                $user->notify(new WeeklyDigest($family, $cache[$key]));
                $sent++;
            }
        }

        $this->info("Sent {$sent} digest email(s).");

        return self::SUCCESS;
    }
}
