<?php

namespace App\Notifications;

use App\Models\Occasion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** "Something new is on the family calendar": sent once, when an occasion is added. */
class OccasionAnnounced extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Occasion $occasion, public ?User $addedBy = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $this->occasion->loadMissing(['family', 'people', 'place']);
        $family = $this->occasion->family;
        $member = $notifiable->membershipIn($family);

        return (new MailMessage)
            ->subject($this->occasion->icon().' '.$this->occasion->title.' · '.$this->occasion->whenLabel())
            ->markdown('mail.occasion-announced', [
                'occasion' => $this->occasion,
                'family' => $family,
                'addedBy' => $this->addedBy?->name ?? 'A relative',
                'url' => route('occasions.show', [$family, $this->occasion]),
                'calendarUrl' => route('calendar.occasion', [$family, $this->occasion]),
                'unsubscribeUrl' => $member ? URL::signedRoute('reminders.unsubscribe', ['member' => $member->id]) : null,
            ]);
    }
}
