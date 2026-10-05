<x-app-layout :title="$event->exists ? 'Edit event' : 'New event'">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-4 text-2xl font-semibold">{{ $event->exists ? 'Edit event' : 'Add a life event' }}</h1>
        <form method="POST" action="{{ $event->exists ? route('events.update', [$family, $event]) : route('events.store', $family) }}" class="card grid gap-4 sm:grid-cols-2" x-data="{ type: @js(old('type', $event->type)) }">
            @csrf
            @if($event->exists) @method('PUT') @else <input type="hidden" name="return_to" value="{{ url()->previous() }}"> @endif
            <div><label class="label">Kind of event</label><select name="type" x-model="type" class="input">@foreach(\App\Models\Event::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
            <div><label class="label">Title</label><input name="title" value="{{ old('title', $event->title) }}" required class="input" placeholder="Moved to Lucknow"></div>
            <div>
                <label class="label">Date</label>
                <div class="flex gap-2">
                    <select name="date_qualifier" class="input !w-28">@foreach(['exact' => 'Exactly', 'about' => 'About', 'before' => 'Before', 'after' => 'After'] as $v => $l)<option value="{{ $v }}" @selected(old('date_qualifier', $event->date_qualifier) === $v)>{{ $l }}</option>@endforeach</select>
                    <input name="date" value="{{ old('date', $event->date) }}" class="input" placeholder="1972">
                </div>
            </div>
            <div><label class="label">Until <span class="normal-case">(optional)</span></label><input name="end_date" value="{{ old('end_date', $event->end_date) }}" class="input"></div>
            <div x-show="type === 'migration'"><label class="label">Moved from</label><input name="from_place" value="{{ old('from_place', $event->fromPlace?->name) }}" class="input" placeholder="Gorakhpur, India"></div>
            <div><label class="label" x-text="type === 'migration' ? 'Moved to' : 'Place'">Place</label><input name="place" value="{{ old('place', $event->place?->name) }}" class="input" placeholder="Lucknow, India"></div>
            <div class="sm:col-span-2"><label class="label">Who was involved?</label><x-people-picker :people="$people" :selected="old('people', $selected)" /></div>
            <div class="sm:col-span-2"><label class="label">What happened</label><textarea name="description" rows="4" class="input" dir="auto">{{ old('description', $event->description) }}</textarea></div>
            <div class="flex gap-2 sm:col-span-2"><button class="btn btn-primary">Save</button><a href="{{ url()->previous() }}" class="btn">Cancel</a></div>
        </form>
        @if($event->exists && ($member->atLeast('editor') || $event->created_by === auth()->id()))
            <form method="POST" action="{{ route('events.destroy', [$family, $event]) }}" class="mt-3" onsubmit="return confirm('Delete this event?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Delete event</button></form>
        @endif
    </div>
</x-app-layout>
