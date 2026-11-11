<?php

namespace App\Notifications;

use App\Models\Occasion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** "Next week" / "tomorrow" / "today" reminder for one occasion. */
class OccasionReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Occasion $occasion, public int $daysUntil, public string $on) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function whenText(): string
    {
        return match (true) {
            $this->daysUntil === 0 => 'today',
            $this->daysUntil === 1 => 'tomorrow',
            default => "in {$this->daysUntil} days",
        };
    }

    public function toMail(User $notifiable): MailMessage
    {
        $this->occasion->loadMissing(['family', 'people', 'place']);
        $family = $this->occasion->family;
        $member = $notifiable->membershipIn($family);
        $mine = $this->occasion->rsvps()->where('user_id', $notifiable->id)->first();

        return (new MailMessage)
            ->subject($this->occasion->icon().' '.$this->occasion->title.' is '.$this->whenText())
            ->markdown('mail.occasion-reminder', [
                'occasion' => $this->occasion,
                'family' => $family,
                'when' => $this->whenText(),
                'years' => $this->occasion->yearsSince(CarbonImmutable::parse($this->on)),
                'mine' => $mine,
                'url' => route('occasions.show', [$family, $this->occasion]),
                'unsubscribeUrl' => $member ? URL::signedRoute('reminders.unsubscribe', ['member' => $member->id]) : null,
            ]);
    }
}
