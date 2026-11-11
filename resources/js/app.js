import Alpine from 'alpinejs';
import { recorder } from './recorder';

window.Alpine = Alpine;
Alpine.data('recorder', recorder);

// Searchable multi-select for tagging people: <div x-data="peoplePicker(options, selected, 'people[]')">
Alpine.data('peoplePicker', (options, selected = [], name = 'people[]', multiple = true) => ({
    options,
    selected: selected.map(Number),
    name,
    multiple,
    q: '',
    open: false,
    get matches() {
        const q = this.q.toLowerCase();
        return this.options.filter((o) => !this.selected.includes(o.id) && o.label.toLowerCase().includes(q)).slice(0, 12);
    },
    label(id) {
        return this.options.find((o) => o.id === id)?.label ?? '#' + id;
    },
    add(id) {
        this.selected = this.multiple ? [...this.selected, id] : [id];
        this.q = '';
        this.open = this.multiple;
    },
    remove(id) {
        this.selected = this.selected.filter((s) => s !== id);
    },
}));

// Theme toggle persisted per browser
Alpine.store('theme', {
    dark: document.documentElement.classList.contains('dark'),
    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        try { localStorage.setItem('theme', this.dark ? 'dark' : 'light'); } catch (e) {}
    },
});

// Installable app: register the service worker and capture the browser's install prompt.
//
// Android browsers fire `beforeinstallprompt`, so we can offer a real install button.
// iPhones and iPads never prompt: the person has to use Share -> "Add to Home Screen"
// themselves, and that option only exists in Safari and in a few other full browsers,
// not in the in-app browsers that WhatsApp, Instagram or Facebook open links in.
// So on iOS we detect the situation and walk them through it instead.
const ua = navigator.userAgent;
const isIpadOs = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1; // iPadOS pretends to be a Mac
const isIos = (/iphone|ipad|ipod/i.test(ua) && !window.MSStream) || isIpadOs;
const iosBrowser = !isIos ? null
    : /FBAN|FBAV|FB_IAB|Instagram|Messenger|Snapchat|TikTok|BytedanceWebview|Twitter|LinkedInApp|Line\//i.test(ua) ? 'in-app'
    : /CriOS/i.test(ua) ? 'chrome'
    : /FxiOS/i.test(ua) ? 'firefox'
    : /EdgiOS/i.test(ua) ? 'edge'
    : /OPT\//i.test(ua) ? 'opera'
    : /Safari/i.test(ua) ? 'safari'
    : 'in-app'; // a WKWebView without the Safari token: some app's embedded browser

const INSTALL_DISMISSED_KEY = 'pwa-install-dismissed-at';
const DISMISS_FOR_DAYS = 30;

Alpine.store('pwa', {
    prompt: null,
    standalone: matchMedia('(display-mode: standalone)').matches || navigator.standalone === true,
    ios: isIos,
    iosBrowser,
    showIosHelp: false,
    dismissedAt: (() => { try { return Number(localStorage.getItem(INSTALL_DISMISSED_KEY)) || 0; } catch (e) { return 0; } })(),
    get canInstall() {
        return !this.standalone && (this.prompt !== null || this.ios);
    },
    // Only iOS gets a proactive banner: Android browsers already show their own install hint.
    get showBanner() {
        return this.ios && !this.standalone && Date.now() - this.dismissedAt > DISMISS_FOR_DAYS * 86400e3;
    },
    get iosInstructions() {
        switch (this.iosBrowser) {
            case 'safari': return { shareHint: 'the Share button at the bottom of the screen (the square with an arrow pointing up)', canInstallHere: true };
            case 'chrome': return { shareHint: 'the Share button at the top right of the address bar (the square with an arrow pointing up)', canInstallHere: true };
            case 'edge': return { shareHint: 'the ··· menu at the bottom, then Share', canInstallHere: true };
            case 'firefox': return { shareHint: 'the ≡ menu at the bottom right, then Share', canInstallHere: true };
            case 'opera': return { shareHint: 'the ··· menu, then Share', canInstallHere: true };
            default: return { shareHint: null, canInstallHere: false };
        }
    },
    async install() {
        if (this.prompt) {
            this.prompt.prompt();
            const { outcome } = await this.prompt.userChoice;
            if (outcome === 'accepted') this.prompt = null;
        } else if (this.ios) {
            this.showIosHelp = true;
        }
    },
    dismissBanner() {
        this.dismissedAt = Date.now();
        try { localStorage.setItem(INSTALL_DISMISSED_KEY, String(this.dismissedAt)); } catch (e) {}
    },
    // Copy the page address so it can be pasted into Safari when we're stuck in an in-app browser.
    async copyLink() {
        try { await navigator.clipboard.writeText(location.href); return true; } catch (e) { return false; }
    },
});
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    Alpine.store('pwa').prompt = e;
});
window.addEventListener('appinstalled', () => {
    Alpine.store('pwa').prompt = null;
    Alpine.store('pwa').standalone = true;
});
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
}

Alpine.start();
