<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Partnership;
use App\Models\Person;
use App\Services\FamilyGraph;
use App\Support\PartialDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FamilyController extends Controller
{
    public function index(Request $request)
    {
        return view('families.index', ['families' => $request->user()->families()->withCount('persons')->get()]);
    }

    public function create()
    {
        return view('families.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'given_name' => 'required|string|max:100',
            'surname' => 'nullable|string|max:100',
            'gender' => 'required|in:male,female,other,unknown',
            'birth_date' => 'nullable|string|max:20',
        ]);
        $slug = Str::slug($data['name']) ?: 'family';
        $base = $slug;
        for ($i = 2; Family::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }
        $family = Family::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'owner_id' => $request->user()->id,
        ]);
        $me = FamilyGraph::for($family)->createPerson([
            'given_name' => $data['given_name'],
            'surname' => $data['surname'] ?? null,
            'gender' => $data['gender'],
            'birth_date' => $data['birth_date'] ?? null,
            'is_living' => true,
        ], null, $request->user()->id);
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $request->user()->id, 'role' => 'owner', 'person_id' => $me->id]);
        $request->user()->update(['current_family_id' => $family->id]);

        return redirect()->route('people.show', [$family, $me])
            ->with('status', 'Your family archive is ready. Start by adding your parents, partner or children.');
    }

    public function show(Family $family)
    {
        $member = $this->member();
        $people = $family->persons()->visibleTo($member)->get();

        return view('families.show', [
            'stats' => [
                'people' => $people->count(),
                'photos' => $family->media()->where('kind', 'photo')->count(),
                'stories' => $family->stories()->count(),
                'documents' => $family->media()->where('kind', 'document')->count(),
                'members' => $family->members()->count(),
                'places' => $family->places()->count(),
            ],
            'upcoming' => $this->upcoming($family, $people),
            'onThisDay' => $this->onThisDay($people),
            'questions' => $family->questions()->where('status', 'open')->with('about')->latest()->take(5)->get(),
            'pendingReviews' => $member->atLeast('editor') ? $family->changeRequests()->where('status', 'pending')->count() : 0,
            'recentStories' => $family->stories()->with('people')->latest()->take(3)->get(),
            'recentPhotos' => $family->media()->where('kind', 'photo')->latest()->take(8)->get(),
            'needsLove' => $people->filter(fn ($p) => ! $p->photo_media_id || ! $p->birth_date || ! $p->biography)
                ->take(30)->map(fn ($p) => ['person' => $p, 'c' => $p->completeness()])
                ->sortBy(fn ($x) => $x['c']['score'])->take(5)->values(),
            'activity' => $family->activity()->with('user')->latest()->take(8)->get(),
            'me' => $member->person,
        ]);
    }

    public function edit(Family $family)
    {
        return view('families.edit');
    }

    public function update(Request $request, Family $family)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'primary_language' => 'required|string|max:8',
            'hide_living_from_viewers' => 'boolean',
        ]);
        $family->update($data + ['hide_living_from_viewers' => $request->boolean('hide_living_from_viewers')]);

        return back()->with('status', 'Family settings saved.');
    }

    /** Birthdays of living relatives, wedding anniversaries and remembrance days in the next 30 days. */
    private function upcoming(Family $family, Collection $people): Collection
    {
        $today = CarbonImmutable::today();
        $items = collect();
        $next = function (string $date) use ($today): ?CarbonImmutable {
            if (! PartialDate::isComplete($date)) {
                return null;
            }
            [$y, $m, $d] = array_map('intval', explode('-', $date));
            if ($m === 2 && $d === 29) {
                $d = 28;
            }
            $candidate = $today->setDate($today->year, $m, $d);
            if ($candidate->lt($today)) {
                $candidate = $candidate->addYear();
            }

            return $candidate->diffInDays($today, true) <= 30 ? $candidate : null;
        };

        foreach ($people as $p) {
            if ($p->is_living && $p->birth_date && ($when = $next($p->birth_date))) {
                $items->push(['when' => $when, 'type' => 'birthday', 'person' => $p, 'years' => $when->year - PartialDate::year($p->birth_date)]);
            }
            if (! $p->is_living && $p->death_date && ($when = $next($p->death_date))) {
                $items->push(['when' => $when, 'type' => 'remembrance', 'person' => $p, 'years' => $when->year - PartialDate::year($p->death_date)]);
            }
        }
        $ids = $people->pluck('id');
        foreach (Partnership::where('family_id', $family->id)->where('status', 'current')->whereNotNull('start_date')->with(['partner1', 'partner2'])->get() as $u) {
            $bothLiving = $u->partner1?->is_living && $u->partner2?->is_living;
            if ($bothLiving && $ids->contains($u->partner1_id) && ($when = $next($u->start_date))) {
                $items->push(['when' => $when, 'type' => 'anniversary', 'partnership' => $u, 'years' => $when->year - PartialDate::year($u->start_date)]);
            }
        }

        return $items->sortBy('when')->values();
    }

    private function onThisDay(Collection $people): Collection
    {
        $md = now()->format('-m-d');

        return $people->flatMap(function (Person $p) use ($md) {
            $out = [];
            if ($p->birth_date && str_ends_with($p->birth_date, $md) && PartialDate::year($p->birth_date) < now()->year) {
                $out[] = ['year' => PartialDate::year($p->birth_date), 'text' => "{$p->full_name} was born", 'person' => $p];
            }
            if ($p->death_date && str_ends_with($p->death_date, $md)) {
                $out[] = ['year' => PartialDate::year($p->death_date), 'text' => "{$p->full_name} passed away", 'person' => $p];
            }

            return $out;
        })->sortBy('year')->values();
    }
}
