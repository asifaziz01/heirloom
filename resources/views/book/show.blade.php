@php use App\Support\PartialDate; $chapterNo = 0; @endphp
<x-layouts.base :title="'The '.$family->name">
<style>
    @page { size: A4; margin: 18mm 16mm; }
    .book { font-family: var(--font-serif); max-width: 820px; margin: 0 auto; padding: 32px 20px; color: #2b2118; background: #fff; }
    .book h2 { font-size: 2rem; margin: 0 0 4px; }
    .chapter { break-before: page; padding-top: 24px; }
    .bio { break-inside: avoid; padding: 16px 0; border-bottom: 1px solid #eadfce; }
    .dark .book { background: #fff; }
</style>
<div class="no-print sticky top-0 z-10 flex items-center justify-between border-b border-line bg-paper px-4 py-2 font-sans text-sm">
    <a href="{{ route('families.show', $family) }}" class="link">← Back to {{ $family->name }}</a>
    <button onclick="window.print()" class="btn btn-primary btn-sm">Print / Save as PDF</button>
</div>
<div class="book">
    <section class="flex min-h-[80vh] flex-col items-center justify-center text-center">
        <p class="tracking-[0.3em] text-stone-500 uppercase">A family history</p>
        <h1 class="mt-4 text-6xl font-semibold">The {{ $family->name }}</h1>
        <p class="mt-4 text-2xl text-stone-600">Our Family Story · {{ $span }}</p>
        @if($family->description)<p class="mt-8 max-w-lg text-stone-600 italic">{{ $family->description }}</p>@endif
        <p class="mt-16 text-sm text-stone-500">{{ $stats['people'] }} people · {{ $stats['generations'] }} generations · {{ $stats['places'] }} places in {{ $stats['countries'] }} {{ \Illuminate\Support\Str::plural('country', $stats['countries']) }} · {{ $stats['stories'] }} stories</p>
    </section>

    <section class="chapter">
        <h2>Contents</h2>
        <ol class="mt-4 space-y-1 text-lg">
            <li>The Family Tree</li>
            @foreach($chapters as $gen => $people)<li>{{ $chapterTitles[$gen] ?? 'Generation '.$gen }} <span class="text-stone-500">({{ $people->count() }})</span></li>@endforeach
            @if($stories->isNotEmpty())<li>Family Stories</li>@endif
            <li>Timeline</li>
        </ol>
    </section>

    @if($pedigree)
        <section class="chapter">
            <h2>The Family Tree</h2>
            <div class="mt-4 overflow-hidden [&_svg]:h-auto [&_svg]:w-full">{!! $pedigree !!}</div>
        </section>
    @endif

    @foreach($chapters as $gen => $people)
        <section class="chapter">
            <p class="text-sm tracking-widest text-stone-500 uppercase">Chapter {{ ++$chapterNo }}</p>
            <h2>{{ $chapterTitles[$gen] ?? 'Generation '.$gen }}</h2>
            @foreach($people as $p)
                <article class="bio flex gap-5">
                    @if($p->photo)<img src="{{ route('media.file', [$p->photo_media_id, 'thumb' => 1]) }}" class="h-36 w-28 shrink-0 rounded object-cover grayscale-[20%]" alt="">@endif
                    <div class="min-w-0">
                        <h3 class="text-xl font-semibold">{{ $p->full_name }} <span class="text-base font-normal text-stone-500">{{ $p->lifespan }}</span></h3>
                        @if($p->names->isNotEmpty())<p class="text-stone-600">@foreach($p->names as $n)<span dir="auto" lang="{{ $n->language }}" class="mr-3">{{ $n->full_name }}</span>@endforeach</p>@endif
                        <p class="text-sm text-stone-600">
                            @if($p->birth_date || $p->birthPlace)Born {{ PartialDate::format($p->birth_date, $p->birth_qualifier) }}{{ $p->birthPlace ? ' in '.$p->birthPlace->label : '' }}. @endif
                            @if(! $p->is_living && ($p->death_date || $p->deathPlace))Died {{ PartialDate::format($p->death_date, $p->death_qualifier) }}{{ $p->deathPlace ? ' in '.$p->deathPlace->label : '' }}. @endif
                            @if($p->occupation){{ $p->occupation }}. @endif
                        </p>
                        @php
                            [$f, $m] = $tree->primaryParents($p->id);
                            $partners = $p->partnerships()->map(fn ($u) => $u->partnerOf($p)?->full_name)->filter();
                            $kids = $p->children()->pluck('given_name');
                        @endphp
                        <p class="text-sm text-stone-600">
                            @if($f || $m)Child of {{ collect([$f?->full_name, $m?->full_name])->filter()->join(' and ') }}. @endif
                            @if($partners->isNotEmpty())Partner of {{ $partners->join(', ', ' and ') }}. @endif
                            @if($kids->isNotEmpty())Parent of {{ $kids->join(', ', ' and ') }}.@endif
                        </p>
                        @if($p->biography)<div class="mt-2 leading-relaxed whitespace-pre-line" dir="auto">{{ $p->biography }}</div>@endif
                    </div>
                </article>
            @endforeach
        </section>
    @endforeach

    @if($stories->isNotEmpty())
        <section class="chapter">
            <h2>Family Stories</h2>
            @foreach($stories as $s)
                <article class="bio">
                    <h3 class="text-xl font-semibold" dir="auto">{{ $s->title }}</h3>
                    @if($s->people->isNotEmpty())<p class="text-sm text-stone-500">{{ $s->people->pluck('full_name')->join(', ') }}</p>@endif
                    <div class="mt-2 leading-relaxed whitespace-pre-line" dir="auto" lang="{{ $s->language }}">{{ $s->body }}</div>
                </article>
            @endforeach
        </section>
    @endif

    <section class="chapter">
        <h2>Timeline</h2>
        <table class="mt-4 w-full text-sm">
            @foreach($timeline as $t)
                <tr class="border-b border-stone-200"><td class="w-32 py-1.5 pr-4 align-top font-semibold">{{ $t['label'] }}</td><td class="py-1.5">{{ $t['title'] }}{{ $t['place'] ? ' — '.$t['place'] : '' }}</td></tr>
            @endforeach
        </table>
        <p class="mt-16 text-center text-xs text-stone-400">Created with Heirloom · {{ now()->format('F Y') }}</p>
    </section>
</div>
</x-layouts.base>
