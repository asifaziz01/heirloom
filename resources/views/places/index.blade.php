<x-app-layout title="Places & migration">
    @push('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    @endpush
    <h1 class="mb-1 text-3xl font-semibold">Places & migration</h1>
    <p class="muted mb-5">Where the family was born, lived and moved: {{ $places->count() }} places across {{ $countries->count() }} {{ \Illuminate\Support\Str::plural('country', $countries->count()) }}.</p>

    @if($countries->isNotEmpty())
        <div class="mb-5 flex flex-wrap gap-2">@foreach($countries as $c => $n)<span class="badge">{{ $c }} · {{ $n }}</span>@endforeach</div>
    @endif

    <div class="card mb-6 !p-0 overflow-hidden">
        <div id="map" class="h-[420px] w-full bg-paper-2 dark:bg-white/5"></div>
        @if($mapData['places']->isEmpty())
            <p class="muted px-5 py-3 text-sm">Add coordinates to places below to see them on the map. Tip: right-click a spot in any online map to copy its latitude and longitude.</p>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card">
            <h2 class="section-title">Migration journeys</h2>
            @forelse($migrations as $e)
                <div class="border-b border-line py-2 text-sm last:border-0 dark:border-white/10">
                    <span class="font-semibold">{{ \App\Support\PartialDate::year($e->date) ?? '—' }}</span>
                    {{ $e->fromPlace->label }} → {{ $e->place->label }}
                    <span class="muted">· {{ $e->people->pluck('given_name')->join(', ') ?: $e->title }}</span>
                </div>
            @empty
                <p class="muted text-sm">Record moves as “Migration / move” events to trace the family’s journey.</p>
            @endforelse
            @if($member->atLeast('contributor'))<a href="{{ route('events.create', [$family, 'type' => 'migration']) }}" class="btn btn-sm mt-3">+ Add a migration</a>@endif
        </section>
        <section class="card">
            <h2 class="section-title">All places</h2>
            @foreach($places as $p)
                <details class="border-b border-line py-2 last:border-0 dark:border-white/10">
                    <summary class="flex cursor-pointer items-center justify-between text-sm">
                        <span>{{ $p->label }} @if($p->hasCoordinates())<span title="On map">📍</span>@endif</span>
                        <span class="muted text-xs">{{ $counts[$p->id] ?? 0 }} births/deaths</span>
                    </summary>
                    @if($member->atLeast('contributor'))
                        <form method="POST" action="{{ route('places.update', [$family, $p]) }}" class="mt-2 grid grid-cols-2 gap-2">
                            @csrf @method('PUT')
                            <input name="name" value="{{ $p->name }}" class="input col-span-2">
                            <input name="country" value="{{ $p->country }}" class="input col-span-2" placeholder="Country">
                            <input name="latitude" value="{{ $p->latitude }}" class="input" placeholder="Latitude e.g. 26.85">
                            <input name="longitude" value="{{ $p->longitude }}" class="input" placeholder="Longitude e.g. 80.95">
                            <button class="btn btn-sm col-span-2">Save place</button>
                        </form>
                    @endif
                </details>
            @endforeach
        </section>
    </div>

    @push('scripts')
        <script>
            (() => {
                const data = @json($mapData);
                if (!window.L || !data.places.length) return;
                const map = L.map('map', { scrollWheelZoom: false });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
                const bounds = [];
                data.places.forEach((p) => {
                    L.circleMarker([p.lat, p.lng], { radius: 6 + Math.min(10, p.count), color: '#8a4b2a', fillOpacity: 0.6 }).addTo(map).bindPopup(`${p.name}<br>${p.count} births/deaths`);
                    bounds.push([p.lat, p.lng]);
                });
                data.routes.forEach((r) => {
                    L.polyline([r.from, r.to], { color: '#4f6b4a', weight: 2.5, dashArray: '6 6' }).addTo(map).bindPopup(r.label);
                });
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 7 });
            })();
        </script>
    @endpush
</x-app-layout>
