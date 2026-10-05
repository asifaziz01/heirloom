<x-app-layout :title="'Ancestors of '.$person->full_name">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('people.show', [$family, $person]) }}" class="link text-sm">← {{ $person->full_name }}</a>
            <h1 class="text-3xl font-semibold">{{ $generations }}-generation family tree</h1>
            <p class="muted text-sm">Download it as an image to share with relatives, or print it as a poster.</p>
        </div>
        <form class="flex items-end gap-2">
            <div><label class="label">Generations</label>
                <select name="generations" class="input !w-20" onchange="this.form.submit()">@foreach([3, 4, 5, 6] as $g)<option @selected($g === $generations)>{{ $g }}</option>@endforeach</select>
            </div>
            <a href="{{ route('tree.pedigree.download', [$family, $person, 'generations' => $generations]) }}" class="btn btn-primary">Download SVG</a>
            <button type="button" onclick="window.print()" class="btn">Print</button>
        </form>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line dark:border-white/10">{!! $svg !!}</div>
</x-app-layout>
