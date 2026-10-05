@php use App\Support\PartialDate; use App\Models\ParentChild; use App\Models\Partnership; use App\Models\Fact; use App\Models\Source; use App\Models\PersonName; @endphp
<x-app-layout :title="$person->full_name">
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-5 md:flex-row md:items-start">
        <x-avatar :person="$person" size="xl" />
        <div class="min-w-0 flex-1">
            <h1 class="text-3xl font-semibold">{{ $person->full_name }}</h1>
            @if($person->nickname)<p class="muted">“{{ $person->nickname }}”</p>@endif
            @if($person->names->isNotEmpty())
                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                    @foreach($person->names as $n)
                        <span class="text-lg" dir="auto" lang="{{ $n->language }}" title="{{ PersonName::TYPES[$n->type] ?? $n->type }} · {{ PersonName::LANGUAGES[$n->language] ?? $n->language }}">{{ $n->full_name }}</span>
                    @endforeach
                </div>
            @endif
            <div class="muted mt-2 space-y-0.5 text-sm">
                @if($person->birth_date || $person->birthPlace)
                    <div>Born {{ PartialDate::format($person->birth_date, $person->birth_qualifier) }}{{ $person->birthPlace ? ' in '.$person->birthPlace->label : '' }}</div>
                @endif
                @if(! $person->is_living)
                    <div>Died {{ PartialDate::format($person->death_date, $person->death_qualifier) ?: '(date unknown)' }}{{ $person->deathPlace ? ' in '.$person->deathPlace->label : '' }}{{ $person->age !== null ? ', aged about '.$person->age : '' }}</div>
                @elseif($person->age !== null)
                    <div>Age {{ $person->age }}</div>
                @endif
                @if($person->occupation)<div>{{ $person->occupation }}{{ $person->education ? ' · '.$person->education : '' }}</div>@endif
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                @if($person->privacy === 'admins')<span class="badge">🔒 Admins only</span>@endif
                @if($person->branch)<span class="badge">{{ $person->branch }} branch</span>@endif
                @if($linkedMember)<span class="badge !bg-moss-soft !text-moss">Member: {{ $linkedMember->user->name }}</span>@endif
            </div>
        </div>
        <div class="w-full shrink-0 md:w-60">
            <div class="mb-1 flex justify-between text-xs"><span class="muted">Profile completeness</span><span class="font-semibold">{{ $completeness['score'] }}%</span></div>
            <div class="h-2 overflow-hidden rounded-full bg-paper-2 dark:bg-white/10"><div class="h-full rounded-full bg-moss" style="width: {{ $completeness['score'] }}%"></div></div>
            @if($completeness['missing'])<p class="muted mt-1 text-xs">Missing: {{ implode(', ', $completeness['missing']) }}</p>@endif
            <div class="mt-3 flex flex-wrap gap-2">
                @if($member->atLeast('contributor'))<a href="{{ route('people.edit', [$family, $person]) }}" class="btn btn-sm">Edit</a>@endif
                <a href="{{ route('people.timeline', [$family, $person]) }}" class="btn btn-sm">Life timeline</a>
                <a href="{{ route('tree.pedigree', [$family, $person]) }}" class="btn btn-sm">Ancestors</a>
                <a href="{{ route('tree.show', [$family, 'root' => $person->id]) }}" class="btn btn-sm">Descendants</a>
                <a href="{{ route('relationship.calculator', [$family, 'b' => $person->id]) }}" class="btn btn-sm">How are we related?</a>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Family focus view --}}
            <section class="card">
                <h2 class="section-title">Family</h2>

                <h3 class="label">Parents</h3>
                <div class="mb-4 grid gap-2 sm:grid-cols-2">
                    @forelse($parents as $p)
                        <div class="relative">
                            <x-person-card :person="$p" :note="$p->link->type !== 'biological' ? ParentChild::TYPES[$p->link->type].' parent' : null" />
                            @if($member->atLeast('contributor'))
                                <form method="POST" action="{{ route('relationships.unlink', [$family, $p->link]) }}" class="absolute top-2 right-2" onsubmit="return confirm('Remove this parent link?')">@csrf @method('DELETE')<button class="muted text-xs hover:text-red-600" title="Remove link">✕</button></form>
                            @endif
                        </div>
                    @empty
                        <p class="muted text-sm">No parents recorded.</p>
                    @endforelse
                </div>

                @if($siblings->isNotEmpty())
                    <h3 class="label">Siblings</h3>
                    <div class="mb-4 grid gap-2 sm:grid-cols-2">
                        @foreach($siblings as $s)
                            <x-person-card :person="$s['person']" :note="$s['kind'] !== 'full' ? ucfirst($s['kind']).'-sibling' : null" />
                        @endforeach
                    </div>
                @endif

                <h3 class="label">Partners & children</h3>
                @forelse($unions as $u)
                    <div class="mb-3 rounded-xl border border-line p-3 dark:border-white/10">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            @if($u['partner'])
                                <x-person-card :person="$u['partner']" class="flex-1" />
                            @else
                                <span class="muted text-sm">Unknown partner</span>
                            @endif
                            <div class="text-right">
                                <span class="badge">{{ $u['partnership']->label() }}</span>
                                @if($member->atLeast('contributor'))<a href="{{ route('partnerships.edit', [$family, $u['partnership']]) }}" class="link ml-1 text-xs">edit</a>@endif
                            </div>
                        </div>
                        @if($u['children']->isNotEmpty())
                            <div class="grid gap-2 border-l-2 border-line pl-3 sm:grid-cols-2 dark:border-white/10">
                                @foreach($u['children'] as $c)
                                    <x-person-card :person="$c" :note="$c->link->type !== 'biological' ? ParentChild::TYPES[$c->link->type] : null" />
                                @endforeach
                            </div>
                        @endif
                        @if($member->atLeast('contributor'))
                            <a href="{{ route('people.create', [$family, 'relation' => 'child', 'of' => $person->id, 'partnership_id' => $u['partnership']->id]) }}" class="link mt-2 inline-block text-xs">+ Add child{{ $u['partner'] ? ' with '.$u['partner']->given_name : '' }}</a>
                        @endif
                    </div>
                @empty
                    <p class="muted mb-3 text-sm">No partners recorded.</p>
                @endforelse
                @if($otherChildren->isNotEmpty())
                    <div class="mb-3 rounded-xl border border-dashed border-line p-3 dark:border-white/10">
                        <div class="muted mb-2 text-xs">Children (other parent not recorded)</div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach($otherChildren as $c)
                                <x-person-card :person="$c" :note="$c->link->type !== 'biological' ? ParentChild::TYPES[$c->link->type] : null" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($member->atLeast('contributor'))
                    <div x-data="{ mode: null, rel: 'parent' }" class="mt-4 border-t border-line pt-4 dark:border-white/10">
                        <div class="flex flex-wrap gap-2">
                            <span class="muted self-center text-sm">Add relative:</span>
                            @foreach(['parent' => 'Parent', 'partner' => 'Partner / spouse', 'child' => 'Child', 'sibling' => 'Sibling'] as $r => $l)
                                <a href="{{ route('people.create', [$family, 'relation' => $r, 'of' => $person->id]) }}" class="btn btn-sm">+ {{ $l }}</a>
                            @endforeach
                            <button type="button" @click="mode = mode ? null : 'link'" class="btn btn-sm">Link someone already in the tree</button>
                        </div>
                        <form x-show="mode === 'link'" x-cloak method="POST" action="{{ route('relationships.link', [$family, $person]) }}" class="mt-3 grid gap-3 rounded-xl bg-paper-2 p-4 sm:grid-cols-2 dark:bg-white/5">
                            @csrf
                            <div>
                                <label class="label">Who is</label>
                                <x-people-picker :people="$people" name="other_id" :multiple="false" />
                            </div>
                            <div>
                                <label class="label">…to {{ $person->given_name }}?</label>
                                <select name="relation" x-model="rel" class="input">
                                    <option value="parent">their parent</option>
                                    <option value="partner">their partner / spouse</option>
                                    <option value="child">their child</option>
                                    <option value="sibling">their sibling (shares parents)</option>
                                </select>
                            </div>
                            <div x-show="rel === 'parent' || rel === 'child'">
                                <label class="label">Type</label>
                                <select name="link_type" class="input">@foreach(ParentChild::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                            </div>
                            <div x-show="rel === 'parent'">
                                <label class="label">Partner of existing parent?</label>
                                <select name="coparent_id" class="input"><option value="">No / not sure</option>@foreach($parents as $p)<option value="{{ $p->id }}">Yes — {{ $p->full_name }}</option>@endforeach</select>
                            </div>
                            <div x-show="rel === 'child'">
                                <label class="label">Child of which partnership?</label>
                                <select name="partnership_id" class="input"><option value="">Other parent unknown</option>@foreach($unions as $u)<option value="{{ $u['partnership']->id }}">With {{ $u['partner']?->full_name ?? 'unknown' }}</option>@endforeach</select>
                            </div>
                            <template x-if="rel === 'partner'">
                                <div class="grid grid-cols-2 gap-3 sm:col-span-2">
                                    <div><label class="label">Type</label><select name="partnership_type" class="input">@foreach(Partnership::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                                    <div><label class="label">Status</label><select name="status" class="input">@foreach(Partnership::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                                    <div><label class="label">From</label><input name="start_date" class="input" placeholder="1958"></div>
                                    <div><label class="label">Until</label><input name="end_date" class="input" placeholder=""></div>
                                </div>
                            </template>
                            <div class="sm:col-span-2"><button class="btn btn-primary">Save relationship</button></div>
                        </form>
                    </div>
                @endif
            </section>

            {{-- Biography --}}
            <section class="card">
                <h2 class="section-title">Life story
                    @if($member->atLeast('contributor') && $family->hasFeature('ai') && \App\Services\HeritageAi::enabled())
                        <form method="POST" action="{{ route('ai.biography', [$family, $person]) }}">@csrf<button class="btn btn-sm">✨ Draft with AI</button></form>
                    @endif
                </h2>
                @if($person->biography)
                    <div class="prose-story" dir="auto">{{ $person->biography }}</div>
                @else
                    <p class="muted text-sm">No biography yet. Write one, or record an interview with someone who knew {{ $person->given_name }}.</p>
                @endif
            </section>

            {{-- Facts & sources --}}
            <section class="card" id="facts">
                <h2 class="section-title">Facts & sources</h2>
                <p class="muted mb-3 text-sm">When relatives remember things differently, record each version with its source. The preferred value is what the profile shows.</p>
                @forelse($factsByField as $field => $facts)
                    <div class="mb-3">
                        <div class="label">{{ Fact::FIELDS[$field] ?? $field }} @if($facts->count() > 1)<span class="badge !bg-amber-100 !text-amber-800 normal-case">{{ $facts->count() }} versions</span>@endif</div>
                        @foreach($facts as $f)
                            <div class="flex flex-wrap items-center gap-2 border-b border-line py-2 text-sm last:border-0 dark:border-white/10">
                                <span class="font-semibold">{{ $f->displayValue() }}</span>
                                @if($f->is_preferred)<span class="badge !bg-moss-soft !text-moss">shown on profile</span>@endif
                                <span class="badge {{ $f->status === 'verified' ? '!bg-moss-soft !text-moss' : ($f->status === 'disputed' ? '!bg-red-100 !text-red-700' : '') }}">{{ Fact::STATUSES[$f->status] }}</span>
                                <span class="badge">{{ Fact::CONFIDENCE[$f->confidence] }} confidence</span>
                                <span class="muted text-xs">{{ $f->source ? 'Source: '.$f->source->title : 'No source' }} · added by {{ $f->addedBy?->name ?? '—' }}</span>
                                @if($member->atLeast('editor'))
                                    <span class="ml-auto flex gap-1">
                                        @unless($f->is_preferred)<form method="POST" action="{{ route('facts.prefer', [$family, $f]) }}">@csrf<button class="btn btn-sm">Use this</button></form>@endunless
                                        <form method="POST" action="{{ route('facts.update', [$family, $f]) }}" class="flex gap-1">@csrf @method('PUT')
                                            <input type="hidden" name="confidence" value="{{ $f->confidence }}">
                                            <select name="status" class="input !w-auto !py-0.5 text-xs" onchange="this.form.submit()">@foreach(Fact::STATUSES as $v => $l)<option value="{{ $v }}" @selected($f->status === $v)>{{ $l }}</option>@endforeach</select>
                                        </form>
                                    </span>
                                @endif
                            </div>
                            @if($f->notes)<p class="muted -mt-1 mb-1 text-xs">{{ $f->notes }}</p>@endif
                        @endforeach
                    </div>
                @empty
                    <p class="muted mb-3 text-sm">No sourced facts yet.</p>
                @endforelse
                @if($member->atLeast('contributor'))
                    <details class="mt-3">
                        <summary class="link cursor-pointer text-sm">+ Record a fact with its source</summary>
                        <form method="POST" action="{{ route('facts.store', [$family, $person]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                            @csrf
                            <div><label class="label">Fact</label><select name="field" class="input">@foreach(Fact::FIELDS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                            <div><label class="label">Value</label><input name="value" required class="input" placeholder="e.g. 1942-03-15 or Lucknow"></div>
                            <div><label class="label">Source</label><input name="source_title" class="input" placeholder="e.g. Birth certificate, Aunt Neha’s memory"></div>
                            <div><label class="label">Source type</label><select name="source_type" class="input">@foreach(Source::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                            <div><label class="label">Confidence</label><select name="confidence" class="input">@foreach(Fact::CONFIDENCE as $v => $l)<option value="{{ $v }}" @selected($v === 'medium')>{{ $l }}</option>@endforeach</select></div>
                            <div><label class="label">Status</label><select name="status" class="input">@foreach(Fact::STATUSES as $v => $l)<option value="{{ $v }}" @selected($v === 'unverified')>{{ $l }}</option>@endforeach</select></div>
                            <div class="sm:col-span-2"><label class="label">Notes</label><input name="notes" class="input"></div>
                            <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_preferred" value="1"> Show this value on the profile</label>
                            <div><button class="btn btn-primary">Save fact</button></div>
                        </form>
                    </details>
                @endif
            </section>

            {{-- Stories --}}
            <section class="card">
                <h2 class="section-title">Stories
                    @if($member->atLeast('contributor'))<a href="{{ route('stories.create', [$family, 'person' => $person->id]) }}" class="btn btn-sm">+ Add story</a>@endif
                </h2>
                @forelse($person->stories as $s)
                    <a href="{{ route('stories.show', [$family, $s]) }}" class="block border-b border-line py-2 last:border-0 dark:border-white/10">
                        <div class="font-semibold">{{ $s->title }}</div>
                        <p class="muted text-sm">{{ $s->excerpt(160) }}</p>
                    </a>
                @empty
                    <p class="muted text-sm">No stories about {{ $person->given_name }} yet.
                        @if($member->atLeast('contributor'))<a class="link" href="{{ route('interviews.create', [$family, 'person' => $person->id]) }}">Record an interview</a>@endif
                    </p>
                @endforelse
            </section>
        </div>

        <div class="space-y-6">
            <section class="card">
                <h2 class="section-title">Photos & documents
                    @if($member->atLeast('contributor'))<a href="{{ route('media.create', [$family, 'person' => $person->id]) }}" class="btn btn-sm">Upload</a>@endif
                </h2>
                @if($media->isNotEmpty())
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($media as $m)
                            <a href="{{ route('media.show', [$family, $m]) }}" title="{{ $m->display_title }}">
                                @if($m->isImage())
                                    <img src="{{ route('media.file', [$m, 'thumb' => 1]) }}" class="aspect-square w-full rounded-lg object-cover" loading="lazy" alt="">
                                @else
                                    <div class="grid aspect-square place-items-center rounded-lg bg-paper-2 text-xs muted dark:bg-white/5">{{ ucfirst($m->kind) }}</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                    <a href="{{ route('media.index', [$family, 'person' => $person->id]) }}" class="link mt-2 inline-block text-sm">See all</a>
                @else
                    <p class="muted text-sm">No photos tagged yet.</p>
                @endif
            </section>

            <section class="card">
                <h2 class="section-title">Timeline <a href="{{ route('people.timeline', [$family, $person]) }}" class="link font-sans text-sm font-normal">Full</a></h2>
                @forelse($timeline as $t)
                    <div class="flex gap-3 py-1.5 text-sm {{ !empty($t['relative']) ? 'muted' : '' }}">
                        <span class="w-12 shrink-0 font-semibold">{{ $t['year'] }}</span>
                        <span>{{ $t['title'] }}@if($t['age'] !== null)<span class="muted text-xs"> · age {{ $t['age'] }}</span>@endif</span>
                    </div>
                @empty
                    <p class="muted text-sm">Add dates to build {{ $person->given_name }}’s timeline.</p>
                @endforelse
                @if($member->atLeast('contributor'))<a href="{{ route('events.create', [$family, 'person' => $person->id]) }}" class="link mt-2 inline-block text-sm">+ Add life event</a>@endif
            </section>

            <section class="card">
                <h2 class="section-title">Names in other languages</h2>
                @foreach($person->names as $n)
                    <div class="flex items-center justify-between py-1 text-sm">
                        <span><span dir="auto" lang="{{ $n->language }}" class="text-base">{{ $n->full_name }}</span> <span class="muted text-xs">{{ PersonName::LANGUAGES[$n->language] ?? $n->language }} · {{ strtolower(PersonName::TYPES[$n->type] ?? $n->type) }}</span></span>
                        @if($member->atLeast('contributor'))
                            <form method="POST" action="{{ route('people.names.destroy', [$family, $person, $n]) }}">@csrf @method('DELETE')<button class="muted text-xs hover:text-red-600">✕</button></form>
                        @endif
                    </div>
                @endforeach
                @if($member->atLeast('contributor'))
                    <form method="POST" action="{{ route('people.names.store', [$family, $person]) }}" class="mt-2 space-y-2">
                        @csrf
                        <input name="full_name" dir="auto" required class="input" placeholder="मोहम्मद अब्दुल रहमान / محمد عبد الرحمن">
                        <div class="flex gap-2">
                            <select name="language" class="input">@foreach(PersonName::LANGUAGES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                            <select name="type" class="input">@foreach(PersonName::TYPES as $v => $l)<option value="{{ $v }}" @selected($v === 'transliteration')>{{ $l }}</option>@endforeach</select>
                        </div>
                        <button class="btn btn-sm">Add name</button>
                    </form>
                @endif
            </section>

            @if($member->atLeast('contributor'))
                <section class="card">
                    <h2 class="section-title">Ask the family</h2>
                    <form method="POST" action="{{ route('questions.store', $family) }}" class="space-y-2">
                        @csrf
                        <input type="hidden" name="about_person_id" value="{{ $person->id }}">
                        <textarea name="question" rows="2" required class="input" placeholder="What do you remember about {{ $person->given_name }}?"></textarea>
                        <button class="btn btn-sm">Post question</button>
                    </form>
                    @if($family->hasFeature('ai') && \App\Services\HeritageAi::enabled())
                        <form method="POST" action="{{ route('ai.questions', [$family, $person]) }}" class="mt-2">@csrf<button class="link text-xs">✨ Suggest interview questions</button></form>
                    @endif
                    @if(session('suggested_questions'))
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach(session('suggested_questions') as $q)
                                <li class="flex items-start justify-between gap-2"><span>{{ $q }}</span>
                                    <form method="POST" action="{{ route('questions.store', $family) }}">@csrf<input type="hidden" name="question" value="{{ $q }}"><input type="hidden" name="about_person_id" value="{{ $person->id }}"><button class="link text-xs">Add</button></form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            @if($member->atLeast('contributor'))
                <form method="POST" action="{{ route('people.destroy', [$family, $person]) }}" onsubmit="return confirm('Remove {{ $person->full_name }} and all their relationship links?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger btn-sm w-full">Remove this person</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
