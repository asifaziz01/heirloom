<x-layouts.base title="You're offline">
    <div class="flex min-h-screen flex-col items-center justify-center px-6 text-center">
        <span class="mb-6 grid size-14 place-items-center rounded-2xl bg-accent font-serif text-2xl text-white">H</span>
        <h1 class="font-serif text-2xl font-semibold">You're offline</h1>
        <p class="muted mt-3 max-w-sm text-sm">Heirloom needs a connection to show your family's archive. Pages you opened recently may still be available. Try again once you're back online.</p>
        <button onclick="location.reload()" class="btn btn-primary mt-6">Try again</button>
        <a href="/" class="link mt-3 text-sm">Go to the home page</a>
    </div>
</x-layouts.base>
