<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class InvitationController extends Controller
{
    public function index(Family $family)
    {
        return view('members.index', [
            'members' => $family->members()->with(['user', 'person'])->get()->sortByDesc(fn ($m) => FamilyMember::ROLES[$m->role]),
            'invitations' => $family->invitations()->whereNull('accepted_at')->with('person')->latest()->get(),
            'people' => $family->persons()->orderBy('given_name')->get(),
        ]);
    }

    public function store(Request $request, Family $family)
    {
        $data = $request->validate([
            'email' => 'nullable|email',
            'role' => ['required', Rule::in(['admin', 'editor', 'contributor', 'viewer'])],
            'person_id' => ['nullable', Rule::exists('persons', 'id')->where('family_id', $family->id)],
        ]);
        if (in_array($data['role'], ['admin', 'editor', 'contributor'], true) && ($msg = $family->limitReached('contributors'))) {
            return back()->withErrors(['role' => $msg]);
        }
        if ($data['role'] === 'admin' && ! $this->member()->atLeast('owner')) {
            return back()->withErrors(['role' => 'Only the family owner can invite administrators.']);
        }
        $invitation = $family->invitations()->create($data + [
            'token' => Str::random(40),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(30),
        ]);

        $link = route('invitations.show', $invitation->token);
        if ($invitation->email) {
            try {
                Mail::to($invitation->email)->send(new InvitationMail($invitation));

                return back()->with('status', "Invitation emailed to {$invitation->email}. You can also share the link directly: {$link}");
            } catch (TransportExceptionInterface $e) {
                report($e);

                return back()->with('status', "The invitation was created but the email could not be sent. Share this link instead: {$link}");
            }
        }

        return back()->with('status', 'Invitation created. Share this link: '.$link);
    }

    public function destroy(Family $family, Invitation $invitation)
    {
        $invitation->delete();

        return back()->with('status', 'Invitation revoked.');
    }

    public function show(string $token)
    {
        $invitation = Invitation::where('token', $token)->with(['family', 'inviter', 'person'])->firstOrFail();
        if (! auth()->check()) {
            session(['url.intended' => route('invitations.show', $token)]);
        }

        return view('members.invitation', ['invitation' => $invitation]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();
        abort_unless($invitation->isUsable(), 410, 'This invitation has expired or was already used.');
        $user = $request->user();
        $existing = $user->membershipIn($invitation->family_id);
        if (! $existing) {
            FamilyMember::create([
                'family_id' => $invitation->family_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'person_id' => $invitation->person_id,
            ]);
        }
        $invitation->update(['accepted_at' => now()]);
        $user->update(['current_family_id' => $invitation->family_id]);

        return redirect()->route('families.show', $invitation->family)->with('status', 'Welcome to the '.$invitation->family->name.' archive!');
    }

    public function updateMember(Request $request, Family $family, FamilyMember $member)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'editor', 'contributor', 'viewer'])],
            'person_id' => ['nullable', Rule::exists('persons', 'id')->where('family_id', $family->id)],
        ]);
        abort_if($member->role === 'owner', 403, 'The owner role cannot be changed here.');
        abort_if($data['role'] === 'admin' && ! $this->member()->atLeast('owner'), 403, 'Only the owner can appoint administrators.');
        $member->update($data);

        return back()->with('status', 'Member updated.');
    }

    public function removeMember(Family $family, FamilyMember $member)
    {
        abort_if($member->role === 'owner', 403, 'The owner cannot be removed.');
        $member->delete();

        return back()->with('status', 'Member removed from the family archive.');
    }
}
