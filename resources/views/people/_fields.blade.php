@php $p = $person ?? null; $q = ['exact' => 'Exactly', 'about' => 'About', 'before' => 'Before', 'after' => 'After']; @endphp
<div x-data="{ living: {{ old('is_living', $p?->is_living ?? true) ? 'true' : 'false' }} }" class="grid gap-4 sm:grid-cols-2">
    <div><label class="label">Given name(s)</label><input name="given_name" value="{{ old('given_name', $p?->given_name) }}" required class="input" dir="auto"></div>
    <div><label class="label">Surname / family name</label><input name="surname" value="{{ old('surname', $p?->surname ?? ($prefillSurname ?? null)) }}" class="input" dir="auto"></div>
    <div><label class="label">Also called (nickname)</label><input name="nickname" value="{{ old('nickname', $p?->nickname) }}" class="input" placeholder="e.g. Nani, Bade Abba"></div>
    <div>
        <label class="label">Gender</label>
        <select name="gender" class="input">
            @foreach(['female' => 'Female', 'male' => 'Male', 'other' => 'Other', 'unknown' => 'Unknown'] as $v => $l)
                <option value="{{ $v }}" @selected(old('gender', $p?->gender ?? ($prefillGender ?? 'unknown')) === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="label">Birth date</label>
        <div class="flex gap-2">
            <select name="birth_qualifier" class="input !w-28">@foreach($q as $v => $l)<option value="{{ $v }}" @selected(old('birth_qualifier', $p?->birth_qualifier) === $v)>{{ $l }}</option>@endforeach</select>
            <input name="birth_date" value="{{ old('birth_date', $p?->birth_date) }}" class="input" placeholder="1942, 1942-03 or 1942-03-15">
        </div>
        <p class="help">A year alone is fine when that’s all anyone remembers.</p>
    </div>
    <div><label class="label">Birthplace</label><input name="birth_place" value="{{ old('birth_place', $p?->birthPlace?->name) }}" class="input" placeholder="Gorakhpur, India"></div>

    <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_living" value="1" x-model="living"> Living</label>
    <template x-if="!living">
        <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
            <div>
                <label class="label">Date of death</label>
                <div class="flex gap-2">
                    <select name="death_qualifier" class="input !w-28">@foreach($q as $v => $l)<option value="{{ $v }}" @selected(old('death_qualifier', $p?->death_qualifier) === $v)>{{ $l }}</option>@endforeach</select>
                    <input name="death_date" value="{{ old('death_date', $p?->death_date) }}" class="input" placeholder="2001-11-02">
                </div>
            </div>
            <div><label class="label">Place of death</label><input name="death_place" value="{{ old('death_place', $p?->deathPlace?->name) }}" class="input"></div>
        </div>
    </template>

    <div><label class="label">Occupation</label><input name="occupation" value="{{ old('occupation', $p?->occupation) }}" class="input" placeholder="Teacher"></div>
    <div><label class="label">Education</label><input name="education" value="{{ old('education', $p?->education) }}" class="input"></div>
    <div><label class="label">Religion / community <span class="normal-case">(optional)</span></label><input name="religion" value="{{ old('religion', $p?->religion) }}" class="input"></div>
    <div><label class="label">Family branch <span class="normal-case">(optional)</span></label><input name="branch" value="{{ old('branch', $p?->branch) }}" class="input" placeholder="e.g. Lucknow branch"></div>

    @unless($p)
        <div class="rounded-xl bg-paper-2 p-3 sm:col-span-2 dark:bg-white/5">
            <label class="label">Name in another language or script <span class="normal-case">(optional)</span></label>
            <div class="grid gap-2 sm:grid-cols-[1fr_9rem]">
                <input name="alt_name" value="{{ old('alt_name') }}" dir="auto" class="input" placeholder="मोहम्मद अब्दुल रहमान · محمد عبد الرحمن">
                <select name="alt_language" class="input">@foreach(\App\Models\PersonName::LANGUAGES as $v => $l)<option value="{{ $v }}" @selected(old('alt_language') === $v)>{{ $l }}</option>@endforeach</select>
            </div>
            <input type="hidden" name="alt_type" value="transliteration">
        </div>
    @endunless

    <div class="sm:col-span-2">
        <label class="label">Biography</label>
        <textarea name="biography" rows="{{ old('biography') ? 12 : 5 }}" class="input" dir="auto" placeholder="Who were they? What do people remember about them?">{{ old('biography', $p?->biography) }}</textarea>
    </div>
    @if($member->atLeast('admin'))
        <div>
            <label class="label">Who can see this profile</label>
            <select name="privacy" class="input">
                <option value="family" @selected(old('privacy', $p?->privacy) === 'family')>Everyone in the family archive</option>
                <option value="admins" @selected(old('privacy', $p?->privacy) === 'admins')>Administrators only</option>
            </select>
        </div>
    @else
        <input type="hidden" name="privacy" value="{{ $p?->privacy ?? 'family' }}">
    @endif
</div>
