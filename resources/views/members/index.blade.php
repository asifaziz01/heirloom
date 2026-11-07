@php use App\Models\FamilyMember; @endphp
<x-app-layout title="Members & invitations">
    <h1 class="mb-1 text-3xl font-semibold">Members & invitations</h1>
    <p class="muted mb-6">One family subscription covers everyone. Invite relatives with the role that fits them.</p>
    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <section class="card">
            <h2 class="section-title">Members</h2>
            @foreach($members as $m)
                <div class="flex flex-wrap items-center gap-3 border-b border-line py-3 last:border-0 dark:border-white/10">
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold">{{ $m->user->name }} <span class="muted text-xs font-normal">{{ $m->user->contact() }}</span></div>
                        <div class="muted text-xs">{{ $m->person ? 'In the tree as '.$m->person->full_name : 'Not linked to a person yet' }}</div>
                    </div>
                    @if($m->role === 'owner')
                        <span class="badge !bg-accent !text-white">Owner</span>
                    @else
                        <form method="POST" action="{{ route('members.update', [$family, $m]) }}" class="flex gap-1">
                            @csrf @method('PUT')
                            <select name="role" class="input !w-auto !py-1 text-xs">@foreach(array_keys(FamilyMember::ROLES) as $r)@if($r !== 'owner')<option value="{{ $r }}" @selected($m->role === $r)>{{ ucfirst($r) }}</option>@endif @endforeach</select>
                            <select name="person_id" class="input !w-36 !py-1 text-xs"><option value="">— person —</option>@foreach($people as $p)<option value="{{ $p->id }}" @selected($m->person_id === $p->id)>{{ $p->full_name }}</option>@endforeach</select>
                            <button class="btn btn-sm">Save</button>
                        </form>
                        <form method="POST" action="{{ route('members.destroy', [$family, $m]) }}" onsubmit="return confirm('Remove {{ $m->user->name }} from this archive?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Remove</button></form>
                    @endif
                </div>
            @endforeach

            @if($invitations->isNotEmpty())
                <h2 class="section-title mt-6">Pending invitations</h2>
                @foreach($invitations as $inv)
                    <div class="flex flex-wrap items-center gap-2 border-b border-line py-2 text-sm last:border-0 dark:border-white/10" x-data="{ copied: false }">
                        <span class="flex-1">{{ $inv->email ?: 'Shareable link' }} · <span class="capitalize">{{ $inv->role }}</span>{{ $inv->person ? ' · as '.$inv->person->full_name : '' }} <span class="muted text-xs">expires {{ $inv->expires_at?->diffForHumans() }}</span></span>
                        <button type="button" class="btn btn-sm" @click="navigator.clipboard.writeText(@js(route('invitations.show', $inv->token))); copied = true" x-text="copied ? 'Copied!' : 'Copy link'"></button>
                        <form method="POST" action="{{ route('invitations.destroy', [$family, $inv]) }}">@csrf @method('DELETE')<button class="btn btn-sm">Revoke</button></form>
                    </div>
                @endforeach
            @endif
        </section>
        <aside class="card self-start">
            <h2 class="section-title">Invite a relative</h2>
            <form method="POST" action="{{ route('invitations.store', $family) }}" class="space-y-3">
                @csrf
                <div><label class="label">Email (optional)</label><input name="email" type="email" class="input" placeholder="cousin@example.com"><p class="help">Or leave blank and share the link on WhatsApp.</p></div>
                <div>
                    <label class="label">Role</label>
                    <select name="role" class="input">@foreach(FamilyMember::ROLE_DESCRIPTIONS as $r => $d)@if($r !== 'owner')<option value="{{ $r }}" @selected($r === 'contributor')>{{ ucfirst($r) }}: {{ $d }}</option>@endif @endforeach</select>
                </div>
                <div><label class="label">They are… (optional)</label><x-people-picker :people="$people" name="person_id" :multiple="false" placeholder="Their profile in the tree" /></div>
                <button class="btn btn-primary w-full">Create invitation link</button>
            </form>
        </aside>
    </div>
</x-app-layout>
