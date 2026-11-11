<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Person;
use App\Services\FamilyGraph;
use App\Services\Upcoming;
use App\Support\PartialDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            'upcoming' => Upcoming::build($family, $people, 30, CarbonImmutable::today()),
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
            'is_public' => 'boolean',
        ]);
        $family->update($data + [
            'hide_living_from_viewers' => $request->boolean('hide_living_from_viewers'),
            'is_public' => $request->boolean('is_public'),
        ]);

        return back()->with('status', 'Family settings saved.');
    }

    /**
     * Permanently deletes the whole archive: every person, link, story, event, file, member and record.
     * Only the owner may do this, and they must type the family's name to confirm.
     */
    public function destroy(Request $request, Family $family)
    {
        abort_unless($family->owner_id === $request->user()->id, 403, 'Only the owner can delete a family archive.');

        $request->validate([
            'confirm_name' => ['required', 'string', function ($attribute, $value, $fail) use ($family) {
                if (mb_strtolower(trim($value)) !== mb_strtolower($family->name)) {
                    $fail('Type the family name exactly as shown to confirm.');
                }
            }],
        ]);

        $name = $family->name;
        $id = $family->id;

        DB::transaction(fn () => $family->delete());

        $disk = Storage::disk('local');
        $disk->deleteDirectory("families/{$id}");
        $disk->deleteDirectory("thumbs/{$id}");

        $user = $request->user()->fresh();
        $next = $user->families()->first();
        $user->update(['current_family_id' => $next?->id]);

        return redirect()->route('families.index')->with('status', "The \"{$name}\" archive and everything in it has been permanently deleted.");
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
