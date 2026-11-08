@props(['title' => null])
@php
    $nav = [
        'Family' => [
            ['families.show', 'Home', 'M3 10.5 12 3l9 7.5V21H3z'],
            ['tree.show', 'Family tree', 'M12 3v6m0 0-5 4m5-4 5 4M7 13v4m10-4v4M4 17h6v4H4zm10 0h6v4h-6z'],
            ['people.index', 'People', 'M16 19v-1a4 4 0 0 0-8 0v1M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6'],
            ['relationship.calculator', 'How are we related?', 'M8 12h8M4 12a4 4 0 0 1 4-4m12 4a4 4 0 0 1-4 4'],
        ],
        'Memories' => [
            ['stories.index', 'Stories', 'M4 5h16v14H4zM8 9h8M8 13h5'],
            ['media.index', 'Photos & files', 'M4 6h16v12H4zM8 14l3-3 5 5'],
            ['interviews.index', 'Record an elder', 'M12 3a3 3 0 0 1 3 3v5a3 3 0 0 1-6 0V6a3 3 0 0 1 3-3m-7 8a7 7 0 0 0 14 0M12 18v3', 'contributor'],
            ['questions.index', 'Family questions', 'M9 9a3 3 0 1 1 3 3v2m0 4h.01'],
        ],
        'History' => [
            ['timeline', 'Timeline', 'M12 3v18M7 7h5M12 12h5M7 17h5'],
            ['places.index', 'Places & migration', 'M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12'],
            ['events.index', 'Life events', 'M5 5h14v15H5zM5 9h14M9 3v4m6-4v4'],
            ['sources.index', 'Sources & evidence', 'M6 3h9l4 4v14H6zM9 12h7M9 16h7'],
            ['book', 'Family book', 'M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z'],
        ],
    ];
    $admin = [
        ['review.index', 'Review queue', 'editor'],
        ['members.index', 'Members & invites', 'admin'],
        ['activity.index', 'Audit history', 'admin'],
        ['export.index', 'Import & export', 'admin'],
        ['families.edit', 'Settings', 'admin'],
    ];
    if (config('services.heritage.billing')) {
        array_splice($admin, 4, 0, [['billing.index', 'Plan', 'admin']]);
    }
    $pending = $member->atLeast('editor') ? $family->changeRequests()->where('status', 'pending')->count() : 0;
@endphp
<x-layouts.base :title="$title">
<div x-data="{ menu: false }" class="lg:grid lg:grid-cols-[250px_1fr]">
    <aside :class="menu ? 'block' : 'hidden'" class="no-print fixed inset-y-0 left-0 z-40 w-[250px] overflow-y-auto border-r border-line bg-paper px-3 py-4 lg:sticky lg:top-0 lg:block lg:h-screen dark:border-white/10 dark:bg-[#1c1712]">
        <a href="{{ route('families.show', $family) }}" class="mb-1 flex items-center gap-2 px-3">
            <span class="grid size-8 place-items-center rounded-lg bg-accent font-serif text-white">A</span>
            <span class="font-serif text-lg font-semibold">Afaq Family</span>
        </a>
        @auth
            <div class="mb-4 px-3" x-data="{ open: false }">
                <button @click="open = !open" class="mt-2 flex w-full items-center justify-between rounded-lg border border-line px-2.5 py-1.5 text-left text-sm dark:border-white/10">
                    <span class="truncate">{{ $family->name }}</span><span class="muted">▾</span>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" class="card mt-1 p-1">
                    @foreach(auth()->user()->families as $f)
                        <a href="{{ route('families.show', $f) }}" class="nav-link {{ $f->id === $family->id ? 'active' : '' }}">{{ $f->name }}</a>
                    @endforeach
                    <a href="{{ route('families.create') }}" class="nav-link">+ New family archive</a>
                </div>
            </div>
        @else
            <div class="mb-4 px-3">
                <div class="mt-2 truncate rounded-lg border border-line px-2.5 py-1.5 text-sm dark:border-white/10">{{ $family->name }}</div>
            </div>
        @endauth
        <form action="{{ route('search', $family) }}" class="mb-4 px-3">
            <input name="q" value="{{ request('q') }}" placeholder="Search people, stories…" class="input">
        </form>
        @foreach($nav as $section => $links)
            <div class="mb-4">
                <div class="mb-1 px-3 text-[11px] font-semibold tracking-wider text-stone-400 uppercase">{{ $section }}</div>
                @foreach($links as $link)
                    @php [$route, $label, $icon] = $link; @endphp
                    @continue(isset($link[3]) && ! $member->atLeast($link[3]))
                    <a href="{{ route($route, $family) }}" class="nav-link {{ request()->routeIs(str_replace('.index', '.*', $route)) ? 'active' : '' }}">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        @endforeach
        @if($member->atLeast('editor'))
            <div class="mb-4">
                <div class="mb-1 px-3 text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Manage</div>
                @foreach($admin as [$route, $label, $role])
                    @if($member->atLeast($role))
                        <a href="{{ route($route, $family) }}" class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}">
                            {{ $label }}
                            @if($route === 'review.index' && $pending)<span class="ml-auto rounded-full bg-accent px-1.5 text-xs text-white">{{ $pending }}</span>@endif
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
        <div class="mt-6 border-t border-line px-3 pt-4 text-sm dark:border-white/10">
            @auth
                <div class="font-medium">{{ auth()->user()->name }}</div>
                <div class="muted text-xs capitalize">{{ $member->isGuest() ? 'Visitor' : $member->role }}{{ config('services.heritage.billing') ? ' · '.config('plans.'.$family->plan.'.name').' plan' : '' }}</div>
                @if($member->isGuest())<p class="muted mt-1 text-xs">You are browsing as a visitor. Ask a family admin for an invitation to add or edit content.</p>@endif
                <div class="mt-3 flex gap-2">
                    <button type="button" @click="$store.theme.toggle()" class="btn btn-sm" x-text="$store.theme.dark ? 'Light' : 'Dark'">Theme</button>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm">Sign out</button></form>
                </div>
            @else
                <div class="font-medium">Browsing as a visitor</div>
                <p class="muted mt-1 text-xs">Family members sign in to add photos, stories and people.</p>
                <div class="mt-3 flex gap-2">
                    <button type="button" @click="$store.theme.toggle()" class="btn btn-sm" x-text="$store.theme.dark ? 'Light' : 'Dark'">Theme</button>
                    <a href="{{ route('login') }}" class="btn btn-sm btn-primary">Sign in</a>
                </div>
            @endauth
            <template x-if="$store.pwa.canInstall">
                <div class="mt-3">
                    <button type="button" @click="$store.pwa.install()" class="btn btn-sm w-full border-accent/40 text-accent">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v3h16v-3"/></svg>
                        Install app
                    </button>
                    <div x-show="$store.pwa.showIosHelp" x-cloak class="muted mt-2 text-xs leading-5">
                        On iPhone or iPad: tap <strong>Share</strong> <span aria-hidden="true">⎙</span> in Safari, then <strong>Add to Home Screen</strong>.
                    </div>
                </div>
            </template>
        </div>
    </aside>
    <div x-show="menu" x-cloak @click="menu = false" class="fixed inset-0 z-30 bg-black/30 lg:hidden"></div>

    <main class="min-w-0">
        <div class="no-print sticky top-0 z-20 flex items-center gap-3 border-b border-line bg-paper/90 px-4 py-2 backdrop-blur lg:hidden dark:border-white/10 dark:bg-[#17130f]/90">
            <button @click="menu = true" class="btn btn-sm" aria-label="Open menu">☰</button>
            <span class="truncate font-serif font-semibold">{{ $family->name }}</span>
        </div>
        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:py-8">
            @if(session('status'))
                <div class="mb-5 rounded-xl border border-moss/30 bg-moss-soft px-4 py-3 text-sm text-moss dark:bg-moss/20 dark:text-green-200">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>
</x-layouts.base>
