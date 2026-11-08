<x-app-layout :title="$story->title">
    <article class="mx-auto max-w-3xl">
        <a href="{{ route('stories.index', $family) }}" class="link text-sm">← Stories</a>
        <h1 class="mt-2 text-4xl leading-tight font-semibold" dir="auto">{{ $story->title }}</h1>
        <p class="muted mt-2 text-sm">
            @if($story->teller)As told by <a class="link" href="{{ route('people.show', [$family, $story->teller]) }}">{{ $story->teller->full_name }}</a> · @endif
            @if($story->story_date){{ \App\Support\PartialDate::format($story->story_date) }} · @endif
            @if($story->place){{ $story->place->label }} · @endif
            Added by {{ $story->author?->name ?? 'a family member' }}
        </p>
        @if($story->audio)
            <div class="card mt-5 !p-3"><audio controls preload="none" src="{{ route('media.file', $story->audio) }}" class="w-full"></audio></div>
        @endif
        <div class="prose-story mt-6 font-serif" dir="auto" lang="{{ $story->language }}">{{ $story->body }}</div>
        @if($story->people->isNotEmpty())
            <div class="mt-8 border-t border-line pt-5 dark:border-white/10">
                <h2 class="label">People in this story</h2>
                <div class="grid gap-2 sm:grid-cols-2">@foreach($story->people as $p)<x-person-card :person="$p" />@endforeach</div>
            </div>
        @endif
        @if($member->canManage($story->created_by))
            <div class="mt-6 flex gap-2">
                <a href="{{ route('stories.edit', [$family, $story]) }}" class="btn btn-sm">Edit</a>
                <form method="POST" action="{{ route('stories.destroy', [$family, $story]) }}" onsubmit="return confirm('Delete this story?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Delete</button></form>
            </div>
        @endif
    </article>
</x-app-layout>
