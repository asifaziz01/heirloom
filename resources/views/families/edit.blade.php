<x-app-layout title="Settings">
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-4 text-3xl font-semibold">Family settings</h1>
        <form method="POST" action="{{ route('families.update', $family) }}" class="card space-y-4">
            @csrf @method('PUT')
            <div><label class="label">Family name</label><input name="name" value="{{ old('name', $family->name) }}" required class="input"></div>
            <div><label class="label">About</label><textarea name="description" rows="3" class="input">{{ old('description', $family->description) }}</textarea></div>
            <div><label class="label">Main language</label><select name="primary_language" class="input">@foreach(\App\Models\PersonName::LANGUAGES as $v => $l)<option value="{{ $v }}" @selected($family->primary_language === $v)>{{ $l }}</option>@endforeach</select></div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="hide_living_from_viewers" value="1" @checked($family->hide_living_from_viewers) class="mt-1"> <span><strong>Hide living relatives from viewers.</strong> People with the viewer role only see profiles of those who have passed away.</span></label>
            <button class="btn btn-primary">Save settings</button>
        </form>
        <div class="card mt-6 text-sm">
            <h2 class="section-title">Privacy promise</h2>
            <ul class="muted list-disc space-y-1 pl-5">
                <li>The archive is private by default. Only invited members can see it.</li>
                <li>Files are served only to signed-in members of this family.</li>
                <li>No advertising, and personal data is never sold.</li>
                <li>Every change is recorded in the audit history.</li>
                <li>The complete archive can be exported at any time.</li>
            </ul>
        </div>
        @if($family->owner_id === auth()->id())
            <div class="card mt-6 border-red-200 text-sm dark:border-red-400/30" x-data="{ open: @js($errors->has('confirm_name')), typed: '' }">
                <h2 class="section-title text-red-700 dark:text-red-300">Delete this tree</h2>
                <p class="muted">This permanently removes the whole archive: every person, relationship, story, event, photo, document, member and the audit history. Nothing can be recovered afterwards. Export the archive first if you want to keep a copy.</p>
                <button type="button" x-show="!open" @click="open = true" class="btn btn-danger btn-sm mt-3">Delete this tree…</button>
                <form x-show="open" x-cloak method="POST" action="{{ route('families.destroy', $family) }}" class="mt-3 space-y-3" onsubmit="return confirm('Delete the whole '{{ addslashes($family->name) }}' archive permanently? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <div>
                        <label class="label">Type <strong>{{ $family->name }}</strong> to confirm</label>
                        <input name="confirm_name" x-model="typed" autocomplete="off" class="input" placeholder="{{ $family->name }}">
                        @error('confirm_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex gap-2">
                        <button class="btn btn-danger btn-sm" :disabled="typed.trim().toLowerCase() !== @js(mb_strtolower($family->name))">Permanently delete everything</button>
                        <button type="button" @click="open = false; typed = ''" class="btn btn-sm">Cancel</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
