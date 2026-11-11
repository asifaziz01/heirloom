<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Http\Request;

/** Whether a member gets reminder and digest emails for a family. */
class ReminderController extends Controller
{
    public function update(Request $request, Family $family)
    {
        $member = $this->member();
        abort_if($member->isGuest(), 403);

        $member->update(['email_reminders' => $request->boolean('email_reminders')]);

        return back()->with('status', $member->email_reminders ? 'You will get reminder emails for this family.' : 'Reminder emails are off for this family.');
    }

    /** Signed link at the foot of every reminder email; works without signing in. */
    public function unsubscribe(FamilyMember $member)
    {
        $member->update(['email_reminders' => false]);

        return view('occasions.unsubscribed', ['family' => $member->family]);
    }
}
