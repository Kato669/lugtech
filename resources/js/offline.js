/**
 * Working offline (Phase 9), the page side. The service worker (public/sw.js) keeps pages and
 * recordings; this file keeps the learner's progress:
 *
 *   - postJson() sends each save with a request id. If there's no signal, saves that can wait
 *     (finished lessons, reviews, games, speaking verdicts) go into a queue on this phone and
 *     the caller gets { queued: true }.
 *   - When the phone is back online (or the next page opens online), the queue is sent with a
 *     fresh security token. The server answers a repeated request id with the first answer, so
 *     nothing is ever counted twice.
 *   - Alpine.store('net'): online or not, and how many saves are waiting (the offline banner).
 *   - lugSaveForOffline(url): save a whole stage's lessons and recordings ahead.
 *   - Alpine.data('installApp'): the "Install the app" button (and the iPhone instructions).
 */
const QUEUE_KEY = 'lug.queue';
const USER_KEY = 'lug.user';
// Saves that may wait for a signal. Anything else (e.g. the speaking checker) needs the network.
const QUEUEABLE = [/\/learn\/[^/]+\/complete$/, /\/review\/\d+$/, /\/games\/[^/]+\/complete$/, /\/speak\/attempts$/];
const MAX_QUEUE = 200;
const MAX_AGE_DAYS = 30;

const store = {
    get() {
        try { return JSON.parse(localStorage.getItem(QUEUE_KEY)) || []; } catch (e) { return []; }
    },
    set(items) {
        try { localStorage.setItem(QUEUE_KEY, JSON.stringify(items.slice(-MAX_QUEUE))); } catch (e) { /* storage full or blocked */ }
    },
};

const userId = () => document.querySelector('meta[name=lug-user]')?.content || null;
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
}));

function send(url, body, id, token) {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Request-Id': id },
        body: JSON.stringify(body),
    });
}

/** POST JSON with the CSRF token. Offline, a save that can wait is queued: resolves to { queued: true }. */
window.postJson = async (url, body) => {
    const id = uuid();
    let res;
    try {
        if (navigator.onLine === false) throw new TypeError('offline');
        res = await send(url, body, id, csrf());
    } catch (e) {
        const path = new URL(url, location.href).pathname;
        if (!(e instanceof TypeError) || !QUEUEABLE.some((r) => r.test(path))) throw e;
        store.set([...store.get(), { id, url, body, user: userId(), at: Date.now() }]);
        Alpine.store('net').refresh();
        return { queued: true };
    }
    if (!res.ok) throw new Error(`Request failed: ${res.status}`);
    return res.json();
};

/** Send whatever was saved offline. Safe to call any time; only one run at once. */
let flushing = false;
async function flush() {
    if (flushing || navigator.onLine === false) return;
    const me = userId();
    const cutoff = Date.now() - MAX_AGE_DAYS * 864e5;
    // Saves made by someone else on this phone, or very old ones, are not sent to this account.
    const queue = store.get().filter((i) => i.user === me && i.at > cutoff);
    store.set(queue);
    Alpine.store('net').refresh();
    if (!queue.length || !me) return;

    flushing = true;
    let sent = 0;
    let last = null;
    try {
        let token = await freshToken();
        if (!token) return; // signed out meanwhile: keep the queue for when they're back
        for (const item of [...queue]) {
            let res;
            try {
                res = await send(item.url, item.body, item.id, token);
                if (res.status === 419) { // the session moved on: one fresh token, one retry
                    token = await freshToken();
                    res = token ? await send(item.url, item.body, item.id, token) : res;
                }
            } catch (e) {
                break; // signal gone again: keep the rest for next time
            }
            if (res.ok) {
                sent++;
                last = await res.json().catch(() => null);
            } else if (res.status >= 500 || res.status === 429 || res.status === 419) {
                break; // try again later
            } // other refusals (e.g. a lesson that no longer exists): drop it
            store.set(store.get().filter((q) => q.id !== item.id)); // sent, or refused for good
        }
    } finally {
        flushing = false;
        Alpine.store('net').refresh();
    }
    if (sent) {
        window.updateCounters?.(last);
        Alpine.store('net').toast(sent === 1 ? 'Progress from offline saved.' : `${sent} things from offline saved.`);
    }
}

async function freshToken() {
    try {
        const res = await fetch('/sync/token', { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!res.ok) return null;
        const data = await res.json();
        return String(data.user_id) === String(userId()) ? data.token : null;
    } catch (e) {
        return null;
    }
}

export default (Alpine) => {
    Alpine.store('net', {
        online: navigator.onLine !== false,
        waiting: 0,
        message: '',
        refresh() { this.waiting = store.get().filter((i) => i.user === userId()).length; },
        toast(text) {
            this.message = text;
            setTimeout(() => (this.message = ''), 4000);
        },
    });

    window.addEventListener('online', () => { Alpine.store('net').online = true; flush(); });
    window.addEventListener('offline', () => { Alpine.store('net').online = false; });
    document.addEventListener('alpine:initialized', () => {
        Alpine.store('net').refresh();
        // A different person on this phone (log in / log out): forget the pages saved for the last one.
        const me = userId() ?? '';
        let before = null;
        try { before = localStorage.getItem(USER_KEY); localStorage.setItem(USER_KEY, me); } catch (e) { /* private mode */ }
        if (before !== null && before !== me) navigator.serviceWorker?.controller?.postMessage({ type: 'clear-pages' });
        // Remember page titles, so the offline page can list saved lessons by name.
        try {
            const titles = JSON.parse(localStorage.getItem('lug.titles') || '{}');
            titles[location.pathname] = document.title.replace(/ · [^·]+$/, '');
            localStorage.setItem('lug.titles', JSON.stringify(titles));
        } catch (e) { /* private mode */ }
        flush();
    });

    if ('serviceWorker' in navigator && window.LUGANDA_SW !== false) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }

    /** Quietly keep some recordings on the phone (e.g. every recording of the lesson being opened). */
    window.lugKeep = (urls) => {
        const list = [...new Set((urls || []).filter((u) => typeof u === 'string' && u.includes('/storage/audio/')))];
        if (list.length) navigator.serviceWorker?.ready.then((r) => r.active?.postMessage({ type: 'save', id: 'keep', urls: list })).catch(() => {});
    };

        /** Save a stage (its lesson pages and recordings) for offline use. */
    window.lugSaveForOffline = (listUrl, onProgress) => new Promise(async (resolve, reject) => {
        const sw = (await navigator.serviceWorker?.ready)?.active;
        if (!sw) return reject(new Error('Offline saving is not available in this browser.'));
        const list = await fetch(listUrl, { headers: { Accept: 'application/json' } }).then((r) => r.json());
        try {
            localStorage.setItem('lug.titles', JSON.stringify({ ...JSON.parse(localStorage.getItem('lug.titles') || '{}'), ...(list.titles || {}) }));
        } catch (e) { /* private mode */ }
        const id = uuid();
        const listener = (e) => {
            if (e.data?.type !== 'save-progress' || e.data.id !== id) return;
            onProgress?.(e.data);
            if (e.data.done === e.data.total) {
                navigator.serviceWorker.removeEventListener('message', listener);
                resolve(e.data);
            }
        };
        navigator.serviceWorker.addEventListener('message', listener);
        sw.postMessage({ type: 'save', id, urls: list.urls });
        if (!list.urls.length) resolve({ done: 0, total: 0, failed: 0 });
    });

    /** "Save for offline" on a stage of the course path. */
    const savedStages = () => { try { return JSON.parse(localStorage.getItem('lug.stages') || '{}'); } catch (e) { return {}; } };
    Alpine.data('saveStage', (listUrl) => ({
        state: savedStages()[listUrl] ? 'saved' : 'idle', // idle | saving | saved | error (remembered on this phone)
        done: 0,
        total: 0,
        failed: 0,
        supported: 'serviceWorker' in navigator,
        async save() {
            this.state = 'saving';
            try {
                const r = await lugSaveForOffline(listUrl, (p) => Object.assign(this, { done: p.done, total: p.total }));
                Object.assign(this, { state: 'saved', failed: r.failed, done: r.done, total: r.total });
                try { localStorage.setItem('lug.stages', JSON.stringify({ ...savedStages(), [listUrl]: Date.now() })); } catch (e) { /* private mode */ }
            } catch (e) {
                this.state = 'error';
            }
        },
    }));

    /** The "Install the app" button. On iPhone/iPad (no install prompt), short instructions instead. */
    let deferred = null;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferred = e;
        window.dispatchEvent(new Event('lug-installable'));
    });
    Alpine.data('installApp', () => ({
        canInstall: !!deferred,
        installed: window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone === true,
        ios: /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream,
        showIos: false,
        init() {
            window.addEventListener('lug-installable', () => (this.canInstall = true));
            window.addEventListener('appinstalled', () => Object.assign(this, { installed: true, canInstall: false }));
        },
        async install() {
            if (!deferred) return;
            deferred.prompt();
            await deferred.userChoice.catch(() => null);
            deferred = null;
            this.canInstall = false;
        },
    }));
};
