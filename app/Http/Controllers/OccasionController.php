<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\Occasion;
use App\Notifications\OccasionAnnounced;
use App\Services\PlaceResolver;
use App\Services\Upcoming;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class OccasionController extends Controller
{
    public function index(Request $request, Family $family)
    {
        $member = $this->member();
        $showPast = $request->boolean('past');
        $q = $family->occasions()->with(['people', 'place', 'rsvps']);
        $occasions = $showPast
            ? $q->past()->orderByDesc('date')->get()
            : $q->current()->get()->sortBy(fn (Occasion $o) => $o->nextOccurrence()->format('Y-m-d'))->values();

        return view('occasions.index', [
            'occasions' => $occasions,
            'showPast' => $showPast,
            'pastCount' => $family->occasions()->past()->count(),
            'upcoming' => Upcoming::forFamily($family, $member, 90)->where('type', '!=', 'occasion')->take(12)->values(),
            'calendarUrl' => $member->isGuest() ? null : route('calendar.feed', $member->calendarToken()),
        ]);
    }

    public function show(Family $family, Occasion $occasion)
    {
        $occasion->load(['people', 'place', 'creator', 'rsvps.user']);

        return view('occasions.show', [
            'occasion' => $occasion,
            'mine' => $occasion->rsvpOf(auth()->user()),
            'headcount' => $occasion->headcount(),
            'shareText' => $occasion->summaryLine()."\n".route('occasions.show', [$family, $occasion]),
        ]);
    }

    public function create(Request $request, Family $family)
    {
        return view('occasions.form', [
            'occasion' => new Occasion(['type' => $request->string('type', 'other')->value(), 'rsvp_enabled' => true, 'send_reminders' => true]),
            'selected' => array_filter([$request->integer('person')]),
            'people' => $family->persons()->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
        ]);
    }

    public function store(Request $request, Family $family)
    {
        $data = $this->validated($request, $family);
        $occasion = $family->occasions()->create($this->attributes($family, $data) + ['created_by' => $request->user()->id]);
        $occasion->people()->sync($data['people'] ?? []);

        // Tell the family straight away; reminders follow closer to the day.
        $recipients = $family->reminderRecipients()->reject(fn ($u) => $u->id === $request->user()->id);
        Notification::send($recipients, new OccasionAnnounced($occasion, $request->user()));

        $note = $recipients->isNotEmpty() ? ' '.$recipients->count().' relative'.($recipients->count() === 1 ? ' has' : 's have').' been emailed.' : '';

        return redirect()->route('occasions.show', [$family, $occasion])->with('status', 'Occasion added.'.$note);
    }

    public function edit(Family $family, Occasion $occasion)
    {
        return view('occasions.form', [
            'occasion' => $occasion->load('place'),
            'selected' => $occasion->people()->pluck('persons.id')->all(),
            'people' => $family->persons()->orderBy('given_name')->get(['id', 'given_name', 'surname', 'birth_date']),
        ]);
    }

    public function update(Request $request, Family $family, Occasion $occasion)
    {
        $data = $this->validated($request, $family);
        $occasion->update($this->attributes($family, $data));
        $occasion->people()->sync($data['people'] ?? []);

        return redirect()->route('occasions.show', [$family, $occasion])->with('status', 'Occasion updated.');
    }

    public function destroy(Family $family, Occasion $occasion)
    {
        abort_unless($this->member()->canManage($occasion->created_by), 403);
        $occasion->delete();

        return redirect()->route('occasions.index', $family)->with('status', 'Occasion removed.');
    }

    /** A member says whether they are coming. Visitors without an account cannot. */
    public function rsvp(Request $request, Family $family, Occasion $occasion)
    {
        abort_if($this->member()->isGuest(), 403, 'Sign in as a family member to reply.');
        abort_unless($occasion->rsvp_enabled, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Occasion::RSVP_STATUSES))],
            'guests' => 'nullable|integer|min:0|max:50',
            'note' => 'nullable|string|max:500',
        ]);
        $occasion->rsvps()->updateOrCreate(['user_id' => $request->user()->id], [
            'status' => $data['status'],
            'guests' => $data['status'] === 'going' ? (int) ($data['guests'] ?? 0) : 0,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', 'Thanks, your reply has been saved.');
    }

    private function validated(Request $request, Family $family): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(Occasion::TYPES))],
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:10000',
            'date' => 'required|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:date',
            'time' => 'nullable|date_format:H:i',
            'repeats_yearly' => 'boolean',
            'place' => 'nullable|string|max:200',
            'venue' => 'nullable|string|max:255',
            'rsvp_enabled' => 'boolean',
            'send_reminders' => 'boolean',
            'people' => 'nullable|array',
            'people.*' => [Rule::exists('persons', 'id')->where('family_id', $family->id)],
        ]);
    }

    private function attributes(Family $family, array $data): array
    {
        return [
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'date' => $data['date'],
            'end_date' => $data['end_date'] ?? null,
            'time' => $data['time'] ?? null,
            'repeats_yearly' => (bool) ($data['repeats_yearly'] ?? false),
            'place_id' => PlaceResolver::resolve($family, $data['place'] ?? null),
            'venue' => $data['venue'] ?? null,
            'rsvp_enabled' => (bool) ($data['rsvp_enabled'] ?? false),
            'send_reminders' => (bool) ($data['send_reminders'] ?? false),
        ];
    }
}
