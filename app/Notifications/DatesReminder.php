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
 * "Today" or "tomorrow" in the family: birthdays, wedding anniversaries and death
 * anniversaries taken from the tree itself, bundled into one email per day.
 * $items is plain data (text, url) so it queues cleanly.
 */
class DatesReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<int, array{text: string, url: string, type: string}>  $items */
    public function __construct(public Family $family, public array $items, public string $when) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $member = $notifiable->membershipIn($this->family);
        $first = $this->items[0]['text'];
        $more = count($this->items) - 1;

        return (new MailMessage)
            ->subject(ucfirst($this->when).': '.$first.($more > 0 ? " and {$more} more" : ''))
            ->markdown('mail.dates-reminder', [
                'family' => $this->family,
                'items' => $this->items,
                'when' => $this->when,
                'url' => route('families.show', $this->family),
                'unsubscribeUrl' => $member ? URL::signedRoute('reminders.unsubscribe', ['member' => $member->id]) : null,
            ]);
    }
}
