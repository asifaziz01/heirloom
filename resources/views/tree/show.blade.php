<x-app-layout title="Family tree">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-semibold">Family tree</h1>
            <p class="muted text-sm">Descendants grouped by each marriage or partnership. Click anyone to open their profile.</p>
        </div>
        <form class="flex flex-wrap items-end gap-2">
            <div>
                <label class="label">Start from</label>
                <select name="root" class="input !w-56" onchange="this.form.submit()">
                    @foreach($people as $p)<option value="{{ $p->id }}" @selected($p->id === $rootId)>{{ $p->full_name }}{{ $p->birth_date ? ' ('.substr($p->birth_date, 0, 4).')' : '' }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Generations</label>
                <select name="depth" class="input !w-20" onchange="this.form.submit()">@foreach(range(1, 8) as $d)<option @selected($d === $depth)>{{ $d }}</option>@endforeach</select>
            </div>
            @if($rootId)<a href="{{ route('tree.pedigree', [$family, $rootId]) }}" class="btn">Ancestor chart</a>@endif
        </form>
    </div>
    @if(count($roots) > 1)
        <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
            <span class="muted">Oldest ancestors:</span>
            @foreach($roots as $r)
                <a href="{{ route('tree.show', [$family, 'root' => $r['person']->id, 'depth' => $depth]) }}" class="badge {{ $r['person']->id === $rootId ? '!bg-accent !text-white' : '' }}">{{ $r['person']->full_name }} · {{ $r['count'] }}</a>
            @endforeach
        </div>
    @endif
    <div class="card overflow-x-auto !p-6" x-data="{ zoom: 1 }" x-init="$nextTick(() => $el.scrollLeft = ($el.scrollWidth - $el.clientWidth) / 2)">
        <div class="no-print mb-3 flex gap-1">
            <button type="button" class="btn btn-sm" @click="zoom = Math.max(0.4, zoom - 0.15)">−</button>
            <button type="button" class="btn btn-sm" @click="zoom = 1">100%</button>
            <button type="button" class="btn btn-sm" @click="zoom = Math.min(1.6, zoom + 0.15)">+</button>
        </div>
        @if($tree)
            <div class="dtree inline-block min-w-full origin-top-left" :style="`zoom: ${zoom}`">
                <ul>@include('tree._node', ['node' => $tree])</ul>
            </div>
        @else
            <p class="muted">Add people to see the tree.</p>
        @endif
    </div>
</x-app-layout>
