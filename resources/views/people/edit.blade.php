<x-app-layout :title="'Edit '.$person->full_name">
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-1 text-2xl font-semibold">Edit {{ $person->full_name }}</h1>
        @if($member->needsReview())<p class="muted mb-4 text-sm">Your changes will be reviewed by a family admin.</p>@endif
        <form method="POST" action="{{ route('people.update', [$family, $person]) }}" class="card mt-4 space-y-6">
            @csrf @method('PUT')
            @include('people._fields', ['person' => $person])
            <div class="flex gap-2">
                <button class="btn btn-primary">{{ $member->needsReview() ? 'Submit for review' : 'Save' }}</button>
                <a href="{{ route('people.show', [$family, $person]) }}" class="btn">Cancel</a>
            </div>
        </form>

        @if($photos->isNotEmpty())
            <div class="card mt-6">
                <h2 class="section-title">Profile photo</h2>
                <p class="muted mb-3 text-sm">Choose from photos {{ $person->given_name }} is tagged in.</p>
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                    @foreach($photos as $m)
                        <form method="POST" action="{{ route('people.photo', [$family, $person]) }}">
                            @csrf <input type="hidden" name="media_id" value="{{ $m->id }}">
                            <button class="block w-full"><img src="{{ route('media.file', [$m, 'thumb' => 1]) }}" class="aspect-square w-full rounded-lg object-cover {{ $person->photo_media_id === $m->id ? 'ring-4 ring-accent' : '' }}" alt=""></button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
