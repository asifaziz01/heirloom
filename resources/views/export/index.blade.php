<x-app-layout title="Import & export">
    <h1 class="mb-1 text-3xl font-semibold">Import & export</h1>
    <p class="muted mb-6">Your family owns its data. Take all of it with you at any time, in open formats.</p>
    <div class="grid gap-6 md:grid-cols-2">
        <section class="card">
            <h2 class="section-title">Complete family archive</h2>
            <p class="mb-3 text-sm">A ZIP with everything: <strong>{{ $counts['people'] }}</strong> people, <strong>{{ $counts['stories'] }}</strong> stories and <strong>{{ $counts['files'] }}</strong> original files (~{{ $counts['mb'] }} MB), plus a GEDCOM tree, a JSON copy of all data, and a README explaining it.</p>
            @if($family->hasFeature('archive_export'))
                <a href="{{ route('export.archive', $family) }}" class="btn btn-primary">Download complete archive</a>
            @else
                <a href="{{ route('billing.index', $family) }}" class="btn">Available on Heritage</a>
            @endif
        </section>
        <section class="card">
            <h2 class="section-title">Family tree (GEDCOM)</h2>
            <p class="mb-3 text-sm">GEDCOM 5.5.1 opens in Ancestry, MyHeritage, FamilySearch, Gramps and almost all genealogy software. Multiple marriages, adoption and step relationships are preserved.</p>
            @if($family->hasFeature('gedcom'))
                <a href="{{ route('export.gedcom', $family) }}" class="btn btn-primary">Download .ged</a>
            @else
                <a href="{{ route('billing.index', $family) }}" class="btn">Available on Heritage</a>
            @endif
        </section>
        <section class="card">
            <h2 class="section-title">Import a GEDCOM file</h2>
            <p class="mb-3 text-sm">Bring in a tree you’ve built elsewhere. People are added as new records, so import into a fresh archive or check for duplicates afterwards.</p>
            <form method="POST" action="{{ route('import.gedcom', $family) }}" enctype="multipart/form-data" class="flex flex-wrap gap-2">
                @csrf
                <input type="file" name="gedcom" accept=".ged,.gedcom,text/plain" required class="input !w-auto">
                <button class="btn btn-primary">Import</button>
            </form>
        </section>
        <section class="card">
            <h2 class="section-title">Family book</h2>
            <p class="mb-3 text-sm">A printable book with chapters for each generation, biographies, photos, stories and the family timeline. Open it and choose “Save as PDF”.</p>
            <a href="{{ route('book', $family) }}" class="btn">Open the family book</a>
        </section>
    </div>
</x-app-layout>
