<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Occasion;
use App\Services\Calendar;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * iCalendar feeds. A phone or calendar app subscribed to the feed shows every occasion,
 * birthday and anniversary and raises its own reminders, with nothing else to set up.
 */
class CalendarController extends Controller
{
    /** One-off download while browsing (members and visitors to a public archive alike). */
    public function download(Family $family): Response
    {
        return $this->ics(Calendar::forFamily($family, $this->member()), Str::slug($family->name).'.ics');
    }

    /** A single occasion, for "Add to my calendar". */
    public function occasion(Family $family, Occasion $occasion): Response
    {
        return $this->ics(Calendar::forOccasion($occasion), Str::slug($occasion->title).'.ics');
    }

    /**
     * Personal subscription address. Calendar apps cannot sign in, so the member's secret
     * token stands in for a session; the feed shows exactly what that member may see.
     */
    public function feed(string $token): Response
    {
        $member = FamilyMember::where('calendar_token', $token)->with('family')->firstOrFail();

        return $this->ics(Calendar::forFamily($member->family, $member), Str::slug($member->family->name).'.ics');
    }

    private function ics(string $body, string $filename): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
