<x-guest-layout title="New family archive">
    <h1 class="mb-1 text-2xl font-semibold">Start a family archive</h1>
    <p class="muted mb-6 text-sm">The archive belongs to the family, not one person. You’ll be its owner and can invite relatives next.</p>
    <form method="POST" action="{{ route('families.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="name">Family name</label>
            <input id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. The Sharma Family" class="input">
        </div>
        <div>
            <label class="label" for="description">About this family <span class="normal-case">(optional)</span></label>
            <textarea id="description" name="description" rows="2" class="input" placeholder="Where the family comes from, who started this archive…">{{ old('description') }}</textarea>
        </div>
        <div class="rounded-xl bg-paper-2 p-4 dark:bg-white/5">
            <div class="mb-3 text-sm font-semibold">First, add yourself</div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Given name</label><input name="given_name" value="{{ old('given_name', strtok(auth()->user()->name, ' ')) }}" required class="input"></div>
                <div><label class="label">Surname</label><input name="surname" value="{{ old('surname') }}" class="input"></div>
                <div>
                    <label class="label">Gender</label>
                    <select name="gender" class="input">
                        @foreach(['female' => 'Female', 'male' => 'Male', 'other' => 'Other', 'unknown' => 'Prefer not to say'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('gender') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label">Birth date</label><input name="birth_date" value="{{ old('birth_date') }}" placeholder="1985-04-12" class="input"></div>
            </div>
        </div>
        <button class="btn btn-primary w-full">Create archive</button>
    </form>
    @if(auth()->user()->families()->exists())
        <p class="mt-5 text-center text-sm"><a class="link" href="{{ route('families.index') }}">Back to my family archives</a></p>
    @endif
</x-guest-layout>
