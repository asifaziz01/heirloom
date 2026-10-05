<x-app-layout title="Family questions">
    <h1 class="mb-1 text-3xl font-semibold">Family questions</h1>
    <p class="muted mb-6">Ask what you’ve always wondered. Anyone who knows can answer with a story, and the answer is kept in the archive.</p>
    <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
        <div class="space-y-6">
            <section class="card">
                <h2 class="section-title">Open questions</h2>
                @forelse($open as $q)
                    <div class="flex items-start justify-between gap-3 border-b border-line py-3 last:border-0 dark:border-white/10">
                        <div>
                            <p class="font-medium">{{ $q->question }}</p>
                            <p class="muted text-xs">{{ $q->about ? 'About '.$q->about->full_name.' · ' : '' }}asked by {{ $q->asker->name }} {{ $q->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            @if($member->atLeast('contributor'))<a href="{{ route('questions.answer', [$family, $q]) }}" class="btn btn-sm btn-primary">Answer</a>@endif
                            @if($member->atLeast('editor') || $q->asked_by === auth()->id())
                                <form method="POST" action="{{ route('questions.destroy', [$family, $q]) }}">@csrf @method('DELETE')<button class="btn btn-sm">✕</button></form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="muted text-sm">No open questions.</p>
                @endforelse
            </section>
            @if($answered->isNotEmpty())
                <section class="card">
                    <h2 class="section-title">Answered</h2>
                    @foreach($answered as $q)
                        <p class="py-1 text-sm">{{ $q->question }} → @if($q->answer)<a class="link" href="{{ route('stories.show', [$family, $q->answer]) }}">{{ $q->answer->title }}</a>@endif</p>
                    @endforeach
                </section>
            @endif
        </div>
        @if($member->atLeast('contributor'))
            <aside class="card self-start">
                <h2 class="section-title">Ask a question</h2>
                <form method="POST" action="{{ route('questions.store', $family) }}" class="space-y-3" x-data="{ q: '' }">
                    @csrf
                    <textarea name="question" x-model="q" rows="3" required class="input" placeholder="How did Dada and Dadi meet?"></textarea>
                    <div><label class="label">About (optional)</label><x-people-picker :people="$people" name="about_person_id" :multiple="false" /></div>
                    <button class="btn btn-primary w-full">Post question</button>
                    <div>
                        <p class="label mt-4">Need ideas?</p>
                        @foreach($starters as $s)
                            <button type="button" @click="q = @js($s)" class="block py-1 text-left text-sm hover:text-accent">{{ $s }}</button>
                        @endforeach
                    </div>
                </form>
            </aside>
        @endif
    </div>
</x-app-layout>
