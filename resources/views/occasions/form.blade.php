<x-app-layout :title="$occasion->exists ? 'Edit occasion' : 'New occasion'">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-1 text-2xl font-semibold">{{ $occasion->exists ? 'Edit occasion' : 'Add an occasion' }}</h1>
        <p class="muted mb-4 text-sm">Something the family is planning or looking forward to. Relatives are emailed when it is added and reminded before the day. For things that already happened, add a <a class="link" href="{{ route('events.create', $family) }}">life event</a> instead.</p>
        <form method="POST" action="{{ $occasion->exists ? route('occasions.update', [$family, $occasion]) : route('occasions.store', $family) }}" class="card grid gap-4 sm:grid-cols-2" x-data="{ type: @js(old('type', $occasion->type)), multi: {{ old('end_date', $occasion->end_date?->toDateString()) ? 'true' : 'false' }} }">
            @csrf
            @if($occasion->exists) @method('PUT') @endif
            <div><label class="label">Kind of occasion</label><select name="type" x-model="type" class="input">@foreach(\App\Models\Occasion::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
            <div><label class="label">Title</label><input name="title" value="{{ old('title', $occasion->title) }}" required class="input" :placeholder="{ wedding: 'Zara & Omar’s wedding', anniversary: 'Ammi & Abbu’s 50th anniversary', reunion: 'Summer reunion in Lucknow', memorial: 'Dada’s barsi', birthday: 'Inaya’s 25th birthday' }[type] || 'What is happening?'"></div>
            <div>
                <label class="label">Date</label>
                <input type="date" name="date" value="{{ old('date', $occasion->date?->toDateString()) }}" required class="input">
                <label class="mt-1.5 flex items-center gap-2 text-xs"><input type="checkbox" x-model="multi" class="rounded"> More than one day</label>
            </div>
            <div><label class="label">Time <span class="normal-case">(optional)</span></label><input type="time" name="time" value="{{ old('time', $occasion->time) }}" class="input"></div>
            <div x-show="multi" x-cloak><label class="label">Last day</label><input type="date" name="end_date" value="{{ old('end_date', $occasion->end_date?->toDateString()) }}" class="input" :disabled="!multi"></div>
            <div class="sm:col-span-2"><label class="flex items-center gap-2 text-sm"><input type="hidden" name="repeats_yearly" value="0"><input type="checkbox" name="repeats_yearly" value="1" @checked(old('repeats_yearly', $occasion->repeats_yearly)) class="rounded"> Repeats every year on this date <span class="muted">(an annual reunion, a death anniversary, a festival the family always gathers for)</span></label></div>
            <div><label class="label">City / place</label><input name="place" value="{{ old('place', $occasion->place?->name) }}" class="input" placeholder="Lucknow, India"></div>
            <div><label class="label">Venue or address <span class="normal-case">(optional)</span></label><input name="venue" value="{{ old('venue', $occasion->venue) }}" class="input" placeholder="Taj Mahal Hotel, Gomti Nagar — or a video-call link"></div>
            <div class="sm:col-span-2"><label class="label">Who is it for?</label><x-people-picker :people="$people" :selected="old('people', $selected)" /></div>
            <div class="sm:col-span-2"><label class="label">Details</label><textarea name="description" rows="5" class="input" dir="auto" placeholder="Programme, dress code, where to stay, who to call…">{{ old('description', $occasion->description) }}</textarea></div>
            <div class="grid gap-2 text-sm sm:col-span-2">
                <label class="flex items-center gap-2"><input type="hidden" name="rsvp_enabled" value="0"><input type="checkbox" name="rsvp_enabled" value="1" @checked(old('rsvp_enabled', $occasion->rsvp_enabled)) class="rounded"> Ask relatives whether they are coming (RSVP)</label>
                <label class="flex items-center gap-2"><input type="hidden" name="send_reminders" value="0"><input type="checkbox" name="send_reminders" value="1" @checked(old('send_reminders', $occasion->send_reminders)) class="rounded"> Email reminders a week before, the day before and on the day</label>
            </div>
            <div class="flex gap-2 sm:col-span-2"><button class="btn btn-primary">{{ $occasion->exists ? 'Save' : 'Add & tell the family' }}</button><a href="{{ $occasion->exists ? route('occasions.show', [$family, $occasion]) : route('occasions.index', $family) }}" class="btn">Cancel</a></div>
        </form>
    </div>
</x-app-layout>
