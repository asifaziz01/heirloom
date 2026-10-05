@php
    use App\Models\ParentChild; use App\Models\Partnership;
    $labels = ['parent' => 'a parent', 'child' => 'a child', 'partner' => 'a partner or spouse', 'sibling' => 'a sibling'];
    $prefillSurname = in_array($relation, ['child', 'sibling'], true) ? $of?->surname : null;
@endphp
<x-app-layout title="Add person">
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-1 text-2xl font-semibold">{{ $of ? 'Add '.$labels[$relation].' of '.$of->full_name : 'Add a person' }}</h1>
        <p class="muted mb-6 text-sm">
            @if($member->needsReview())Your addition will be reviewed by a family admin before it appears.@endif
            Already in the tree? @if($of)<a class="link" href="{{ route('people.show', [$family, $of]) }}#">Link them from {{ $of->given_name }}’s profile</a>@else search first to avoid duplicates.@endif
        </p>
        <form method="POST" action="{{ route('people.store', $family) }}" class="card space-y-6">
            @csrf
            @if($of)
                <input type="hidden" name="relation" value="{{ $relation }}">
                <input type="hidden" name="of" value="{{ $of->id }}">
                <div class="grid gap-4 rounded-xl bg-accent-soft/50 p-4 sm:grid-cols-2 dark:bg-white/5">
                    @if(in_array($relation, ['parent', 'child'], true))
                        <div>
                            <label class="label">Relationship type</label>
                            <select name="link_type" class="input">@foreach(ParentChild::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                        </div>
                    @endif
                    @if($relation === 'parent' && $ofParents->isNotEmpty())
                        <div>
                            <label class="label">Partner of {{ $ofParents->pluck('given_name')->join(' / ') }}?</label>
                            <select name="coparent_id" class="input">
                                <option value="">No / not known</option>
                                @foreach($ofParents as $p)<option value="{{ $p->id }}" @selected($loop->first && $ofParents->count() === 1)>Yes — partner of {{ $p->full_name }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    @if($relation === 'child')
                        <div>
                            <label class="label">Other parent</label>
                            <select name="partnership_id" class="input">
                                <option value="">Unknown / not recorded</option>
                                @foreach($partnerships as $u)
                                    <option value="{{ $u->id }}" @selected((int) request('partnership_id') === $u->id || ($partnerships->count() === 1 && ! request('partnership_id')))>{{ $u->partnerOf($of)?->full_name ?? 'Unknown partner' }} ({{ $u->label() }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if($relation === 'partner')
                        <div><label class="label">Type</label><select name="partnership_type" class="input">@foreach(Partnership::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                        <div><label class="label">Status</label><select name="status" class="input">@foreach(Partnership::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                        <div><label class="label">Married / together from</label><input name="start_date" class="input" placeholder="1958-05-02"></div>
                        <div><label class="label">Until <span class="normal-case">(if ended)</span></label><input name="end_date" class="input"></div>
                    @endif
                    @if($relation === 'sibling')
                        <p class="muted text-sm sm:col-span-2">The new person will share {{ $of->given_name }}’s recorded parents ({{ $ofParents->pluck('full_name')->join(', ') ?: 'none recorded yet; add a parent first' }}). For a half-sibling, add them as a child of the shared parent instead.</p>
                    @endif
                </div>
            @endif
            @include('people._fields', ['person' => null, 'prefillGender' => $relation === 'parent' ? null : null])
            <div class="flex gap-2">
                <button class="btn btn-primary">{{ $member->needsReview() ? 'Submit for review' : 'Add to family' }}</button>
                <a href="{{ url()->previous() }}" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
