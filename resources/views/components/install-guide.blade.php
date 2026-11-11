{{--
    "Add to Home Screen" help for iPhones and iPads.

    iOS never shows an install prompt, so this renders two things, both driven by the `pwa` Alpine store:
      - a dismissable banner at the bottom of the screen on iOS when the site isn't already running as an app;
      - a step-by-step sheet opened by the banner or by any "Install app" button, tailored to the browser in use
        (Safari, Chrome, Firefox, Edge, Opera) or telling people stuck in an in-app browser to open the page in Safari.
--}}
<div x-data="{ copied: false }" class="no-print">
    {{-- Bottom banner --}}
    <div x-show="$store.pwa.showBanner" x-cloak x-transition.opacity
         class="fixed inset-x-0 bottom-0 z-40 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <div class="mx-auto flex max-w-md items-center gap-3 rounded-2xl border border-line bg-white p-3 shadow-lg dark:border-white/10 dark:bg-[#231c16]">
            <img src="/icons/apple-touch-icon.png" alt="" width="44" height="44" class="size-11 shrink-0 rounded-xl">
            <div class="min-w-0 flex-1">
                <div class="text-sm font-medium">Add Afaq Family to your Home Screen</div>
                <p class="muted text-xs leading-4">Opens like an app, full screen, with its own icon.</p>
            </div>
            <button type="button" @click="$store.pwa.showIosHelp = true" class="btn btn-sm btn-primary shrink-0">How</button>
            <button type="button" @click="$store.pwa.dismissBanner()" class="muted -mr-1 shrink-0 p-1 text-lg leading-none" aria-label="Not now">&times;</button>
        </div>
    </div>

    {{-- Step-by-step sheet --}}
    <div x-show="$store.pwa.showIosHelp" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
         role="dialog" aria-modal="true" aria-labelledby="install-guide-title" @keydown.escape.window="$store.pwa.showIosHelp = false">
        <div class="absolute inset-0 bg-black/40" @click="$store.pwa.showIosHelp = false"></div>
        <div x-transition class="relative w-full max-w-md rounded-t-3xl bg-white p-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] shadow-xl sm:rounded-3xl dark:bg-[#231c16]">
            <button type="button" @click="$store.pwa.showIosHelp = false" class="muted absolute top-4 right-4 p-1 text-xl leading-none" aria-label="Close">&times;</button>
            <div class="mb-4 flex items-center gap-3">
                <img src="/icons/apple-touch-icon.png" alt="" width="48" height="48" class="size-12 rounded-xl">
                <div>
                    <h2 id="install-guide-title" class="font-serif text-lg font-semibold">Install Afaq Family</h2>
                    <p class="muted text-xs">Free, no App Store needed.</p>
                </div>
            </div>

            <template x-if="$store.pwa.iosInstructions.canInstallHere">
                <ol class="space-y-3 text-sm">
                    <li class="flex gap-3">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-accent text-xs font-semibold text-white">1</span>
                        <span>Tap <span x-text="$store.pwa.iosInstructions.shareHint"></span>
                            <svg class="inline size-5 align-text-bottom text-sky-600" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V4m0 0L8 8m4-4 4 4M5 11v9h14v-9"/></svg>.
                        </span>
                    </li>
                    <li class="flex gap-3">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-accent text-xs font-semibold text-white">2</span>
                        <span>Scroll down the list and tap <strong>Add to Home Screen</strong>
                            <svg class="inline size-5 align-text-bottom" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M12 8v8m-4-4h8"/></svg>.
                        </span>
                    </li>
                    <li class="flex gap-3">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-accent text-xs font-semibold text-white">3</span>
                        <span>Tap <strong>Add</strong> in the top corner. Afaq Family now opens from your Home Screen like any other app.</span>
                    </li>
                </ol>
            </template>

            <template x-if="!$store.pwa.iosInstructions.canInstallHere">
                <div class="text-sm">
                    <p>This page is open inside another app's browser, which can't add sites to the Home Screen. Open it in Safari first:</p>
                    <ol class="mt-3 space-y-3">
                        <li class="flex gap-3">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-accent text-xs font-semibold text-white">1</span>
                            <span>Tap the <strong>&middot;&middot;&middot;</strong> or <strong>Share</strong> button and choose <strong>Open in Safari</strong>, or copy the link below and paste it into Safari.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-accent text-xs font-semibold text-white">2</span>
                            <span>In Safari, tap <strong>Share</strong>, then <strong>Add to Home Screen</strong>, then <strong>Add</strong>.</span>
                        </li>
                    </ol>
                    <button type="button" class="btn mt-4 w-full"
                            @click="$store.pwa.copyLink().then(ok => { copied = ok; setTimeout(() => copied = false, 2500) })"
                            x-text="copied ? 'Link copied' : 'Copy link to this page'">Copy link to this page</button>
                </div>
            </template>

            <button type="button" @click="$store.pwa.showIosHelp = false; $store.pwa.dismissBanner()" class="btn btn-primary mt-5 w-full">Got it</button>
        </div>
    </div>
</div>
