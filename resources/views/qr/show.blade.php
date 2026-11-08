<x-app-layout title="QR code">
    <div class="no-print mx-auto max-w-3xl">
        <h1 class="mb-1 text-3xl font-semibold">QR code for the family home page</h1>
        <p class="muted mb-6">Print it on a reunion poster, a wedding invitation or the back of a framed photo. Anyone who points their phone camera at it lands on <a class="link break-all" href="{{ $url }}">{{ $url }}</a>.</p>

        @unless($family->is_public)
            <div class="card mb-6 border-amber-300/60 bg-amber-50 text-sm dark:border-amber-400/30 dark:bg-amber-400/10">
                <strong>This archive is members only.</strong> Relatives who scan the code are asked to sign in, and only invited members can get in. To let anyone with the code browse, tick <a class="link" href="{{ route('families.edit', $family) }}">Open the archive to visitors</a> in Settings.
            </div>
        @endunless

        <div class="grid gap-6 md:grid-cols-[auto_1fr]">
            <section class="card flex flex-col items-center gap-3">
                <div class="qr-preview w-56 rounded-xl bg-white p-3 text-black sm:w-64">{!! $svg !!}</div>
                <div class="text-center">
                    <div class="font-serif text-lg font-semibold">{{ $family->name }}</div>
                    <div class="muted text-xs">Scan to open the family archive</div>
                </div>
            </section>
            <div class="space-y-6">
                <section class="card" x-data="{ size: '1024' }">
                    <h2 class="section-title">Download for printing</h2>
                    <p class="muted mb-3 text-sm">PNG is right for most uses. Choose a larger size for posters; the code stays sharp at any size as an SVG.</p>
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="label" for="qr-size">PNG size</label>
                            <select id="qr-size" x-model="size" class="input !w-auto">
                                @foreach($sizes as $size)
                                    <option value="{{ $size }}">{{ $size }} × {{ $size }} px{{ $size === 1024 ? ' (recommended)' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <a :href="@js(route('qr.download', [$family, 'format' => 'png'])) + '&size=' + size" href="{{ route('qr.download', [$family, 'format' => 'png', 'size' => 1024]) }}" class="btn btn-primary">Download PNG</a>
                        <a href="{{ route('qr.download', [$family, 'format' => 'svg']) }}" class="btn">Download SVG</a>
                        <button type="button" onclick="window.print()" class="btn">Print</button>
                    </div>
                </section>
                <section class="card text-sm">
                    <h2 class="section-title">Tips</h2>
                    <ul class="muted list-disc space-y-1 pl-5">
                        <li>Print the code at least 3 cm wide so phones can read it from a comfortable distance.</li>
                        <li>Keep a white margin around it and avoid placing it over a photo or pattern.</li>
                        <li>The code encodes the archive's address, so it keeps working after you add people, photos or stories. It stops working if the family's web address changes.</li>
                        <li>Scan a printed copy with your own phone before handing them out.</li>
                    </ul>
                </section>
            </div>
        </div>
    </div>

    {{-- Printable poster: only this block is visible when the page is printed --}}
    <div class="print-only hidden">
        <div class="qr-poster">
            <div class="qr-poster-code">{!! $svg !!}</div>
            <div class="qr-poster-name">{{ $family->name }}</div>
            <div class="qr-poster-sub">Scan to open our family archive</div>
            <div class="qr-poster-url">{{ $url }}</div>
        </div>
    </div>

    @push('head')
        <style>
            .qr-preview svg, .qr-poster-code svg { display: block; width: 100%; height: auto; }
            @media print {
                @page { margin: 2cm; }
                .print-only { display: block !important; }
                main { grid-column: 1 / -1; }
                .qr-poster { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 80vh; text-align: center; color: #000; }
                .qr-poster-code { width: 12cm; max-width: 80%; }
                .qr-poster-name { margin-top: 1.2cm; font-family: Georgia, 'Times New Roman', serif; font-size: 28pt; font-weight: 600; }
                .qr-poster-sub { margin-top: 0.3cm; font-size: 14pt; }
                .qr-poster-url { margin-top: 0.5cm; font-size: 11pt; color: #555; word-break: break-all; }
            }
        </style>
    @endpush
</x-app-layout>
