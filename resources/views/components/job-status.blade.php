{{--
    "Working on it" banner for a queued job. Polls the given status URL (JSON: status, error, url)
    every few seconds and reloads the page, or follows "url", once the job is done or failed.
    Jobs run from a once-a-minute cron on shared hosting, so a wait of up to a minute is normal.
--}}
@props(['url', 'title' => 'Working on it…', 'note' => 'This usually takes a minute or two. You can leave this page and come back; the result will be waiting here.'])
<div {{ $attributes->merge(['class' => 'card flex items-start gap-3 border-accent/40']) }} role="status" aria-live="polite"
     x-data="{
        async poll() {
            try {
                const r = await fetch(@js($url), { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
                if (r.ok) {
                    const d = await r.json();
                    if (d.status === 'done' || d.status === 'failed') {
                        d.url ? window.location.assign(d.url) : window.location.reload();
                        return;
                    }
                }
            } catch (e) {}
            setTimeout(() => this.poll(), 5000);
        }
     }" x-init="setTimeout(() => poll(), 4000)">
    <span class="mt-0.5 inline-block size-4 shrink-0 animate-spin rounded-full border-2 border-accent border-t-transparent" aria-hidden="true"></span>
    <div class="text-sm"><strong>{{ $title }}</strong> <span class="muted">{{ $note }}</span>{{ $slot }}</div>
</div>
