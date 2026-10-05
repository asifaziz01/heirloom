@php $p = $node['person']; @endphp
<li>
    <div class="flex items-start gap-1">
        @include('tree._box', ['p' => $p, 'linkType' => $node['link_type'] ?? null])
        @foreach($node['unions'] as $u)
            @if($u['partner'])
                <span class="mt-5 text-xs muted" title="{{ $u['partnership']->label() }}">{{ in_array($u['partnership']->status, ['divorced', 'separated', 'annulled', 'ended']) ? '⟂' : '♥' }}</span>
                @include('tree._box', ['p' => $u['partner'], 'linkType' => null, 'spouse' => true])
            @endif
        @endforeach
    </div>
    @if($node['more'])
        <a href="{{ route('tree.show', [$family, 'root' => $p->id, 'depth' => $depth]) }}" class="link mt-1 text-xs">more ↓</a>
    @endif
    @php $groups = collect($node['unions'])->filter(fn ($u) => count($u['children'])); @endphp
    @if($groups->isNotEmpty())
        <ul>
            @foreach($groups as $u)
                @if($groups->count() > 1)
                    {{-- several partnerships: label each family group --}}
                    <li>
                        <div class="rounded-md bg-paper-2 px-2 py-0.5 text-[11px] muted dark:bg-white/10">with {{ $u['partner']?->given_name ?? 'unknown' }}</div>
                        <ul>
                            @foreach($u['children'] as $child)
                                @include('tree._node', ['node' => $child])
                            @endforeach
                        </ul>
                    </li>
                @else
                    @foreach($u['children'] as $child)
                        @include('tree._node', ['node' => $child])
                    @endforeach
                @endif
            @endforeach
        </ul>
    @endif
</li>
