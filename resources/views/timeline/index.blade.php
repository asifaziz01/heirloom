<x-app-layout title="Family timeline">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-semibold">Family timeline</h1>
            <p class="muted">{{ $count }} moments in the family’s history, decade by decade.</p>
        </div>
        <form class="flex flex-wrap items-end gap-2">
            <div><label class="label">From</label><input name="from" value="{{ request('from') }}" class="input !w-24" placeholder="1900"></div>
            <div><label class="label">To</label><input name="to" value="{{ request('to') }}" class="input !w-24" placeholder="{{ now()->year }}"></div>
            <select name="types[]" class="input !w-auto">
                <option value="">All moments</option>
                @foreach(['birth' => 'Births', 'death' => 'Deaths', 'marriage' => 'Marriages', 'migration' => 'Migrations', 'story' => 'Stories', 'photo' => 'Photos'] as $v => $l)
                    <option value="{{ $v }}" @selected(in_array($v, (array) request('types')))>{{ $l }}</option>
                @endforeach
            </select>
            <button class="btn">Filter</button>
        </form>
    </div>
    @forelse($byDecade as $decade => $items)
        <section class="mb-8 grid gap-4 md:grid-cols-[8rem_1fr]">
            <h2 class="font-serif text-3xl font-semibold text-accent md:sticky md:top-6 md:self-start">{{ $decade }}s</h2>
            <x-timeline-list :items="$items" />
        </section>
    @empty
        <p class="muted">Add birth dates, marriages and events to see the family’s story unfold here.</p>
    @endforelse
</x-app-layout>
