@php $ex = $interview->extraction; @endphp
<x-app-layout :title="$interview->title">
    <a href="{{ route('interviews.index', $family) }}" class="link text-sm">← Interviews</a>
    <h1 class="mt-1 text-3xl font-semibold">{{ $interview->title }}</h1>
    <p class="muted mb-6">{{ $interview->person ? 'With '.$interview->person->full_name.' · ' : '' }}{{ \App\Models\PersonName::LANGUAGES[$interview->language] ?? $interview->language }}</p>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div x-data="recorder({ saveUrl: @js(route('interviews.update', [$family, $interview])), transcript: @js($interview->transcript ?? ''), lang: @js($interview->language) })" class="space-y-4">
            <section class="card">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" x-show="!recording" @click="start()" class="btn btn-primary">● Start recording</button>
                    <button type="button" x-show="recording" x-cloak @click="stop()" class="btn btn-danger">■ Stop</button>
                    <span x-show="recording" x-cloak class="flex items-center gap-2 text-sm"><span class="size-2.5 animate-pulse rounded-full bg-red-600"></span><span x-text="clock"></span></span>
                    <span class="muted text-xs" x-show="!speechSupported">Live transcription isn’t available in this browser. The audio is still recorded, and you can type or paste a transcript.</span>
                </div>
                <template x-if="audioUrl"><audio :src="audioUrl" controls class="mt-3 w-full"></audio></template>
                @if($interview->audio)
                    <div class="mt-3"><p class="label">Saved recording</p><audio controls preload="none" src="{{ route('media.file', $interview->audio) }}" class="w-full"></audio></div>
                @endif
            </section>

            <section class="card">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-serif text-lg font-semibold">Transcript</h2>
                    <span class="muted text-xs">You can edit freely: fix names, add what the transcription missed.</span>
                </div>
                <textarea x-model="transcript" rows="16" class="input leading-7" dir="auto" lang="{{ $interview->language }}" placeholder="The conversation appears here as you record, or type and paste it."></textarea>
                <p x-show="interim" x-text="interim" class="muted mt-1 text-sm italic"></p>
                <div class="mt-3 flex items-center gap-3">
                    <button type="button" @click="save()" :disabled="saving" class="btn btn-primary" x-text="saving ? 'Saving…' : (blob ? 'Save transcript & recording' : 'Save transcript')"></button>
                    <span x-text="status" class="text-sm"></span>
                </div>
            </section>

            @if($interview->isExtracting())
                <x-job-status :url="route('interviews.status', [$family, $interview])" title="Extracting people, places, dates and a story from the transcript…" />
            @elseif($aiEnabled && filled($interview->transcript))
                <form method="POST" action="{{ route('interviews.extract', [$family, $interview]) }}" class="card flex flex-wrap items-center justify-between gap-3">
                    @csrf
                    <div class="text-sm">
                        <strong>Turn this into family history.</strong> AI finds people, places, dates and events and drafts a story. Nothing is saved until you confirm.
                        @if($interview->status === 'failed' && $interview->extraction_error)
                            <p class="mt-1 text-red-700 dark:text-red-300">Last attempt failed: {{ $interview->extraction_error }}</p>
                        @endif
                    </div>
                    <button class="btn btn-primary">✨ {{ $ex || $interview->status === 'failed' ? 'Extract again' : 'Extract' }}</button>
                </form>
            @elseif(! $aiEnabled)
                <div class="card muted text-sm">AI extraction is off (set <code>ANTHROPIC_API_KEY</code> on a plan with AI). You can still save the transcript and <a class="link" href="{{ route('stories.create', [$family, 'teller' => $interview->person_id, 'person' => $interview->person_id]) }}">write it up as a story</a>.</div>
            @endif

            @if($ex)
                <form method="POST" action="{{ route('interviews.apply', [$family, $interview]) }}" class="card space-y-6 border-accent/40">
                    @csrf
                    <div>
                        <h2 class="section-title">Review what the AI found</h2>
                        <p class="muted text-sm">{{ $ex['summary'] ?? '' }}</p>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 font-semibold"><input type="checkbox" name="story[include]" value="1" checked> Save as a story</label>
                        <input name="story[title]" value="{{ $ex['story_title'] ?? '' }}" class="input mt-2" dir="auto">
                        <textarea name="story[body]" rows="10" class="input mt-2 leading-7" dir="auto">{{ $ex['story_body'] ?? '' }}</textarea>
                    </div>

                    @if(!empty($ex['people']))
                        <div>
                            <h3 class="label">People mentioned</h3>
                            <div class="space-y-2">
                                @foreach($ex['people'] as $i => $p)
                                    @php $match = $p['matched_person_id'] ? ($people[$p['matched_person_id']] ?? null) : null; @endphp
                                    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-line p-2 text-sm dark:border-white/10">
                                        <input type="checkbox" name="people[{{ $i }}][include]" value="1" checked>
                                        @if($match)
                                            <span><strong>{{ $p['name'] }}</strong> → link to existing <a class="link" href="{{ route('people.show', [$family, $match]) }}" target="_blank">{{ $match->full_name }}</a></span>
                                        @else
                                            <input name="people[{{ $i }}][name]" value="{{ $p['name'] }}" class="input !w-48 !py-1" dir="auto">
                                            <input name="people[{{ $i }}][relation]" value="{{ $p['relationship_to_interviewee'] }}" class="input !w-40 !py-1" placeholder="relationship">
                                            <input name="people[{{ $i }}][birth_date]" value="{{ $p['birth_date'] }}" class="input !w-28 !py-1" placeholder="born">
                                            <span class="badge">new person</span>
                                        @endif
                                        @if($p['notes'])<span class="muted w-full text-xs">{{ $p['notes'] }}</span>@endif
                                    </div>
                                @endforeach
                            </div>
                            <p class="help">New people who are a parent, child, partner or sibling of {{ $interview->person?->given_name ?? 'the interviewee' }} are linked automatically.</p>
                        </div>
                    @endif

                    @if(!empty($ex['events']))
                        <div>
                            <h3 class="label">Events for the timeline</h3>
                            @foreach($ex['events'] as $i => $e)
                                @continue(in_array($e['type'], ['birth', 'death', 'marriage']))
                                <div class="flex flex-wrap items-center gap-2 border-b border-line py-2 text-sm last:border-0 dark:border-white/10">
                                    <input type="checkbox" name="events[{{ $i }}][include]" value="1" checked>
                                    <input name="events[{{ $i }}][date]" value="{{ $e['date'] }}" class="input !w-28 !py-1" placeholder="date">
                                    <input name="events[{{ $i }}][title]" value="{{ $e['title'] }}" class="input !w-auto flex-1 !py-1">
                                    <span class="muted text-xs">{{ $e['from_place'] ? $e['from_place'].' → ' : '' }}{{ $e['place'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($ex['follow_up_questions']))
                        <div>
                            <label class="flex items-center gap-2 font-semibold"><input type="checkbox" name="add_questions" value="1" checked> Add follow-up questions for next time</label>
                            <ul class="muted mt-2 list-disc pl-6 text-sm">@foreach($ex['follow_up_questions'] as $q)<li>{{ $q }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <button class="btn btn-primary">Add selected items to the archive</button>
                </form>
            @endif
        </div>

        <aside class="card self-start lg:sticky lg:top-6" x-data>
            <h2 class="section-title">Question guide</h2>
            <p class="muted mb-3 text-xs">Tap a question to mark it in the transcript, then ask it aloud.</p>
            @foreach($guide as $theme => $questions)
                <details class="mb-2" @if($loop->first) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold">{{ $theme }}</summary>
                    @foreach($questions as $q)
                        <button type="button" class="block py-1 text-left text-sm hover:text-accent" onclick="Alpine.$data(document.querySelector('[x-data^=recorder]')).markQuestion(@js($q))">{{ $q }}</button>
                    @endforeach
                </details>
            @endforeach
            <p class="muted mt-4 text-xs">Tips: sit somewhere quiet, keep the phone close, follow their stories wherever they lead, and ask “who else was there?”</p>
        </aside>
    </div>
</x-app-layout>
