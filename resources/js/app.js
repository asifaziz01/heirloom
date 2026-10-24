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
Alpine.store('pwa', {
    prompt: null,
    standalone: matchMedia('(display-mode: standalone)').matches || navigator.standalone === true,
    ios: /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream,
    showIosHelp: false,
    get canInstall() {
        return !this.standalone && (this.prompt !== null || this.ios);
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
