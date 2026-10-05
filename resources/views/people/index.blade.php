<x-app-layout title="People">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-semibold">People <span class="muted font-sans text-base font-normal">{{ $people->count() }}</span></h1>
        @if($member->atLeast('contributor'))<a href="{{ route('people.create', $family) }}" class="btn btn-primary">+ Add person</a>@endif
    </div>
    <form class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search names in any language…" class="input !w-64">
        <select name="filter" class="input !w-auto" onchange="this.form.submit()">
            @foreach(['' => 'Everyone', 'living' => 'Living', 'deceased' => 'Deceased', 'no-photo' => 'Missing a photo', 'no-birth' => 'Missing birth date'] as $v => $l)
                <option value="{{ $v }}" @selected(request('filter') === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <select name="sort" class="input !w-auto" onchange="this.form.submit()">
            <option value="name">Sort by name</option>
            <option value="born" @selected(request('sort') === 'born')>Sort by birth</option>
        </select>
        <button class="btn">Search</button>
    </form>
    @if($surnames->count() > 1)
        <div class="mb-5 flex flex-wrap gap-1.5">
            @foreach($surnames as $s => $n)
                <a href="{{ route('people.index', [$family, 'surname' => $s]) }}" class="badge {{ request('surname') === $s ? '!bg-accent !text-white' : '' }}">{{ $s }} · {{ $n }}</a>
            @endforeach
            @if(request('surname'))<a href="{{ route('people.index', $family) }}" class="link text-xs">clear</a>@endif
        </div>
    @endif
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($people as $p)
            <x-person-card :person="$p" :note="$p->birthPlace?->name" />
        @empty
            <p class="muted">No one matches.</p>
        @endforelse
    </div>
</x-app-layout>
