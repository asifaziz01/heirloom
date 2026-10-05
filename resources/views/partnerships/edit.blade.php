@php use App\Models\Partnership; $u = $partnership; @endphp
<x-app-layout title="Edit partnership">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-4 text-2xl font-semibold">{{ $u->partner1->full_name }} & {{ $u->partner2?->full_name ?? 'unknown partner' }}</h1>
        <form method="POST" action="{{ route('partnerships.update', [$family, $u]) }}" class="card grid gap-4 sm:grid-cols-2">
            @csrf @method('PUT')
            <div><label class="label">Type</label><select name="type" class="input">@foreach(Partnership::TYPES as $v => $l)<option value="{{ $v }}" @selected($u->type === $v)>{{ $l }}</option>@endforeach</select></div>
            <div><label class="label">Status</label><select name="status" class="input">@foreach(Partnership::STATUSES as $v => $l)<option value="{{ $v }}" @selected($u->status === $v)>{{ $l }}</option>@endforeach</select></div>
            <div><label class="label">Started</label><input name="start_date" value="{{ $u->start_date }}" class="input" placeholder="1958-05-02"></div>
            <div><label class="label">Ended</label><input name="end_date" value="{{ $u->end_date }}" class="input"></div>
            <div class="sm:col-span-2"><label class="label">Place</label><input name="place" value="{{ $u->place?->name }}" class="input"></div>
            <div class="sm:col-span-2"><label class="label">Notes</label><textarea name="notes" rows="3" class="input">{{ $u->notes }}</textarea></div>
            <div class="flex gap-2 sm:col-span-2">
                <button class="btn btn-primary">{{ $member->needsReview() ? 'Submit for review' : 'Save' }}</button>
                <a href="{{ route('people.show', [$family, $u->partner1]) }}" class="btn">Cancel</a>
            </div>
        </form>
        <form method="POST" action="{{ route('partnerships.destroy', [$family, $u]) }}" class="mt-4" onsubmit="return confirm('Remove this partnership? Children keep their links to each parent.')">
            @csrf @method('DELETE')
            <button class="btn btn-danger btn-sm">Remove partnership</button>
        </form>
    </div>
</x-app-layout>
