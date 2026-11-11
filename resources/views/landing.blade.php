<x-layouts.base title="Preserve your family's story for generations">
<header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5">
    <div class="flex items-center gap-2">
        <span class="grid size-9 place-items-center rounded-lg bg-accent font-serif text-lg text-white">A</span>
        <span class="font-serif text-xl font-semibold">Afaq Family</span>
    </div>
    <nav class="flex items-center gap-2">
        <a href="{{ route('login') }}" class="btn">Sign in</a>
        <a href="{{ route('register') }}" class="btn btn-primary">Start free</a>
    </nav>
</header>

<section class="mx-auto max-w-6xl px-5 pt-10 pb-16 md:pt-20">
    <p class="badge mb-5">Private family heritage platform</p>
    <h1 class="max-w-3xl text-4xl leading-tight font-semibold md:text-6xl">Preserve your family’s story for generations.</h1>
    <p class="muted mt-6 max-w-2xl text-lg leading-relaxed">Build your family tree, collect photographs and documents, record the stories only your elders know, and create a private archive your grandchildren will inherit.</p>
    <div x-data class="mt-8 flex flex-wrap gap-3">
        <a href="{{ route('register') }}" class="btn btn-primary px-5 py-2.5 text-base">Start your family archive</a>
        <a href="#how" class="btn px-5 py-2.5 text-base">How it works</a>
        <template x-if="$store.pwa.canInstall">
            <button type="button" @click="$store.pwa.install()" class="btn px-5 py-2.5 text-base">Install the app</button>
        </template>
    </div>
</section>

@if(isset($publicFamilies) && $publicFamilies->isNotEmpty())
<section class="mx-auto max-w-6xl px-5 pb-16">
    <h2 class="mb-2 text-3xl font-semibold">Browse a family archive</h2>
    <p class="muted mb-6">These families have opened their archives to visitors. No account is needed to look around.</p>
    <div class="grid gap-4 md:grid-cols-3">
        @foreach($publicFamilies as $pf)
            <a href="{{ route('families.show', $pf) }}" class="card hover:border-accent/50">
                <div class="font-serif text-xl font-semibold">{{ $pf->name }}</div>
                @if($pf->description)<p class="muted mt-1 line-clamp-2 text-sm">{{ $pf->description }}</p>@endif
                <p class="muted mt-2 text-xs">{{ number_format($pf->persons_count) }} {{ Str::plural('person', $pf->persons_count) }}</p>
            </a>
        @endforeach
    </div>
</section>
@endif

<section id="how" class="border-y border-line bg-white py-16 dark:border-white/10 dark:bg-white/5">
    <div class="mx-auto grid max-w-6xl gap-8 px-5 md:grid-cols-3">
        @foreach([
            ['Real families, accurately', 'Multiple marriages, divorce, remarriage, half-siblings, adoption, step and foster relationships, unknown parents. Your tree reflects your family as it really is.'],
            ['Record Grandma’s story', 'Press record and talk. Heirloom transcribes the conversation and helps turn it into stories, people, places and timeline events. You confirm every detail.'],
            ['Facts with sources', 'Relatives remember different dates. Keep every version with its source and confidence, and mark which one is verified.'],
            ['Family-owned and private', 'One subscription for the whole family. Contributors suggest changes; admins approve them. No ads, no selling data.'],
            ['Spread across countries', 'Names in English, Hindi, Urdu and more. Migration maps and timelines that keep a diaspora family connected to its roots.'],
            ['Pass it on', 'Download everything (GEDCOM, photos, documents, stories) whenever you like, or print a family book.'],
        ] as [$h, $p])
            <div>
                <h3 class="mb-2 text-xl font-semibold">{{ $h }}</h3>
                <p class="muted leading-relaxed">{{ $p }}</p>
            </div>
        @endforeach
    </div>
</section>

@if(config('services.heritage.billing'))
<section class="mx-auto max-w-6xl px-5 py-16">
    <h2 class="mb-8 text-3xl font-semibold">One plan for the whole family</h2>
    <div class="grid gap-4 md:grid-cols-4">
        @foreach(config('plans') as $key => $plan)
            <div class="card {{ $key === 'heritage' ? 'ring-2 ring-accent' : '' }}">
                <div class="font-serif text-lg font-semibold">{{ $plan['name'] }}</div>
                <div class="my-2 text-3xl font-semibold">${{ $plan['price'] }}<span class="muted text-sm font-normal">/year</span></div>
                <ul class="muted space-y-1 text-sm">
                    <li>{{ $plan['people'] ? number_format($plan['people']).' people' : 'Unlimited people' }}</li>
                    <li>{{ $plan['storage_mb'] >= 1024 ? ($plan['storage_mb'] / 1024).' GB' : $plan['storage_mb'].' MB' }} storage</li>
                    <li>{{ $plan['contributors'] ? $plan['contributors'].' contributors' : 'Unlimited family members' }}</li>
                    @if(in_array('ai', $plan['features']))<li>AI interviews & biographies</li>@endif
                    @if(in_array('archive_export', $plan['features']))<li>GEDCOM + full archive export</li>@endif
                    @if(in_array('book', $plan['features']))<li>Family book</li>@endif
                </ul>
            </div>
        @endforeach
    </div>
</section>
@endif
<footer class="muted border-t border-line py-8 text-center text-sm dark:border-white/10">Afaq Family · Your family’s private digital heritage</footer>
</x-layouts.base>
