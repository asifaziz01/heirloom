<?php

namespace App\Notifications;

use App\Models\Family;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Monday morning: the birthdays, anniversaries, remembrance days and occasions of the
 * coming week. $items is plain data (date label, text, url) so it queues cleanly.
 */
class WeeklyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<int, array{date: string, text: string, url: string}>  $items */
    public function __construct(public Family $family, public array $items) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $member = $notifiable->membershipIn($this->family);
        $count = count($this->items);

        return (new MailMessage)
            ->subject("This week in the {$this->family->name}: {$count} ".($count === 1 ? 'date' : 'dates').' to remember')
            ->markdown('mail.weekly-digest', [
                'family' => $this->family,
                'items' => $this->items,
                'url' => route('occasions.index', $this->family),
                'unsubscribeUrl' => $member ? URL::signedRoute('reminders.unsubscribe', ['member' => $member->id]) : null,
            ]);
    }
}
