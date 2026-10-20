/*
 * Luganda app service worker (Phase 9): the app keeps working without a signal.
 *
 *   - The app itself (built CSS/JS, icons, the offline page) is saved on install.
 *   - Learner pages (the course path, lessons, review, phrasebook, games, dictionary, and the
 *     public word and phrase pages from Phase 11: /dictionary/amazzi, /say/thank-you) are
 *     network-first: always fresh when online, the last saved copy when offline.
 *   - Recordings are cache-first (their file names never change) and can be saved ahead,
 *     a whole stage at a time ("Save for offline").
 *   - Admin, Check, Studio, login and anything that isn't a plain GET is never cached.
 *
 * Progress made offline is not handled here: the page queues it (resources/js/offline.js).
 * Bump VERSION when this file changes in a way that needs old app files cleared.
 */
const VERSION = 'v4'; // v2 (Phase 11): word and phrase pages; v3 (Phase 12): daily reminders; v4 (Phase 15): culture pages
const APP = `app-${VERSION}`;
const PAGES = 'pages';
const AUDIO = 'audio';
const MAX_AUDIO = 600;      // recordings kept on the phone (oldest go first)
const MAX_PAGES = 120;      // pages kept

const SHELL = ['/offline', '/manifest.webmanifest', '/app-icons/icon-192.png', '/app-icons/icon-512.png', '/app-icons/maskable-512.png', '/favicon.ico'];
const LEARNER_PAGES = [/^\/$/, /^\/learn(\/[^/]+)?$/, /^\/review$/, /^\/phrasebook$/, /^\/games(\/[^/]+)?$/, /^\/dictionary(\/[^/]+)?$/, /^\/say(\/[^/]+)?$/, /^\/culture(\/.*)?$/];
const NEVER = [/^\/(admin|check|studio|login|register|logout|speak|sync)(\/|$)/];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(APP);
        await cache.addAll(SHELL);
        // The built CSS/JS, from Vite's manifest (absent while `npm run dev` is used: then nothing to save).
        try {
            const manifest = await (await fetch('/build/manifest.json', { cache: 'no-store' })).json();
            const files = new Set();
            Object.values(manifest).forEach((e) => { files.add('/build/' + e.file); (e.css || []).forEach((c) => files.add('/build/' + c)); });
            await cache.addAll([...files]);
        } catch (e) { /* development build: fine */ }
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) {
            if (key.startsWith('app-') && key !== APP) await caches.delete(key);
        }
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== self.location.origin) return;
    if (NEVER.some((r) => r.test(url.pathname))) return;

    if (url.pathname.startsWith('/storage/audio/') || url.pathname.startsWith('/music/')) {
        event.respondWith(audio(req)); // recordings and the background music: kept whole, served in byte ranges
    } else if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/app-icons/')) {
        event.respondWith(cacheFirst(req, APP));
    } else if (req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html')) {
        if (LEARNER_PAGES.some((r) => r.test(url.pathname))) event.respondWith(page(req));
        else event.respondWith(fetch(req).catch(() => offlinePage()));
    }
});

/** Pages: fresh when online; the saved copy (or the offline page) without a signal. */
async function page(req) {
    const cache = await caches.open(PAGES);
    const key = pageKey(req.url);
    try {
        const res = await fetch(req);
        if (res.ok && !res.redirected && res.type === 'basic') {
            await cache.put(key, res.clone());
            trim(PAGES, MAX_PAGES);
        }
        return res;
    } catch (e) {
        return (await cache.match(key)) || offlinePage();
    }
}

/** The same page whatever ?source=… it was opened with. */
function pageKey(href) {
    const u = new URL(href);
    u.searchParams.delete('source');
    u.searchParams.delete('entry');
    return u.toString();
}

async function offlinePage() {
    return (await caches.match('/offline')) || new Response('You are offline.', { status: 503, headers: { 'Content-Type': 'text/plain' } });
}

async function cacheFirst(req, name) {
    const cache = await caches.open(name);
    const hit = await cache.match(req);
    if (hit) return hit;
    const res = await fetch(req);
    if (res.ok) await cache.put(req, res.clone());
    return res;
}

/** Recordings: saved whole, then served whole or as the byte range the audio player asks for. */
async function audio(req) {
    const cache = await caches.open(AUDIO);
    const key = req.url;
    let res = await cache.match(key);
    if (!res) {
        try {
            res = await fetch(key); // whole file, no Range: a 206 can't be stored
        } catch (e) {
            return new Response('', { status: 504 });
        }
        if (!res.ok) return res;
        await cache.put(key, res.clone());
        trim(AUDIO, MAX_AUDIO);
    }
    const range = req.headers.get('range');
    if (!range) return res;
    const buf = await res.arrayBuffer();
    const m = /bytes=(\d*)-(\d*)/.exec(range);
    const size = buf.byteLength;
    let start = m && m[1] !== '' ? Number(m[1]) : 0;
    let end = m && m[2] !== '' ? Number(m[2]) : size - 1;
    if (m && m[1] === '' && m[2] !== '') { start = Math.max(0, size - Number(m[2])); end = size - 1; }
    end = Math.min(end, size - 1);
    return new Response(buf.slice(start, end + 1), {
        status: 206,
        headers: {
            'Content-Type': res.headers.get('Content-Type') || 'audio/mpeg',
            'Content-Range': `bytes ${start}-${end}/${size}`,
            'Content-Length': String(end - start + 1),
            'Accept-Ranges': 'bytes',
        },
    });
}

/** Keep a cache to a size, dropping the oldest entries first. */
async function trim(name, max) {
    const cache = await caches.open(name);
    const keys = await cache.keys();
    for (let i = 0; i < keys.length - max; i++) await cache.delete(keys[i]);
}

/** Messages from the page: save a list of pages and recordings ahead, or forget saved pages. */
self.addEventListener('message', (event) => {
    const msg = event.data || {};
    if (msg.type === 'save') {
        event.waitUntil(save(msg.urls || [], event.source, msg.id));
    } else if (msg.type === 'clear-pages') {
        event.waitUntil(caches.delete(PAGES));
    }
});

async function save(urls, client, id) {
    let done = 0;
    let failed = 0;
    const report = () => client && client.postMessage({ type: 'save-progress', id, done, failed, total: urls.length });
    for (const href of urls) {
        const url = new URL(href, self.location.origin);
        try {
            if (url.pathname.startsWith('/storage/audio/')) {
                const cache = await caches.open(AUDIO);
                if (!(await cache.match(url.toString()))) {
                    const res = await fetch(url.toString());
                    if (!res.ok) throw new Error(res.status);
                    await cache.put(url.toString(), res);
                }
            } else {
                const res = await fetch(url.toString(), { credentials: 'same-origin' });
                if (!res.ok || res.redirected) throw new Error(res.status);
                await (await caches.open(PAGES)).put(pageKey(url.toString()), res);
            }
        } catch (e) {
            failed++;
        }
        done++;
        if (done % 3 === 0 || done === urls.length) report();
    }
    trim(AUDIO, MAX_AUDIO);
    trim(PAGES, MAX_PAGES);
    report();
}

/*
 * Daily reminders (Phase 12). The app sends an empty push (no data, so nothing to decrypt):
 * show a fixed note. Tapping it opens the course path (or brings an open window forward).
 */
self.addEventListener('push', (event) => {
    event.waitUntil(self.registration.showNotification('Time for a little Luganda 🥁', {
        body: 'A few minutes today keeps your streak going.',
        icon: '/app-icons/icon-192.png',
        badge: '/app-icons/icon-192.png',
        tag: 'daily-reminder',
        data: { url: '/learn' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/learn', self.location.origin).href;
    event.waitUntil((async () => {
        for (const client of await self.clients.matchAll({ type: 'window', includeUncontrolled: true })) {
            if (client.url.startsWith(self.location.origin) && 'focus' in client) {
                await client.navigate?.(url).catch(() => {});
                return client.focus();
            }
        }
        return self.clients.openWindow(url);
    })());
});
