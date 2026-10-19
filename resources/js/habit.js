/**
 * The daily habit (Phase 12).
 *
 *  - Practice clock: on lesson, review and game pages (window.LUGANDA_PRACTICE), count the seconds
 *    the page is on screen and the learner did something in the last minute (or a recording is playing).
 *  - Each save that can wait (lesson, review, game, speaking) carries those seconds as `active_seconds`,
 *    so practice made offline is counted too when the queue is sent (resources/js/offline.js).
 *  - Toasts for tasks done, all tasks done and the daily goal (from the `habit` part of a save's answer).
 *  - Alpine.data('reminders'): the daily reminder switch in Settings (web push; needs HTTPS).
 */
const SAVES = [/\/learn\/[^/]+\/complete$/, /\/review\/\d+$/, /\/games\/[^/]+\/complete$/, /\/speak\/attempts$/];
const IDLE_MS = 60_000;

let seconds = 0;
let lastAction = Date.now();

function startClock(Alpine) {
    if (!window.LUGANDA_PRACTICE) return;
    for (const type of ['pointerdown', 'keydown', 'touchstart', 'wheel']) {
        addEventListener(type, () => (lastAction = Date.now()), { passive: true, capture: true });
    }
    setInterval(() => {
        if (document.visibilityState !== 'visible') return;
        const listening = Alpine.store('audio')?.state === 'playing';
        if (listening || Date.now() - lastAction < IDLE_MS) seconds++;
    }, 1000);
}

/** The practice time since the last save (and start counting again). */
window.lugPracticeTake = () => {
    const s = seconds;
    seconds = 0;
    return s;
};

function announce(Alpine, habit) {
    if (!habit) return;
    const lines = [];
    for (const task of habit.tasks_done || []) lines.push(`✓ Task done: ${task}`);
    if (habit.all_done) lines.push('🎉 All three tasks done today!');
    if (habit.goal_met) lines.push(`🎯 Daily goal reached: ${habit.goal} minutes!`);
    if (!lines.length) return;
    Alpine.store('habit').show(lines.join(' · ') + (habit.cowries_earned ? ` +${habit.cowries_earned} 🐚` : ''));
}

export default (Alpine) => {
    Alpine.store('habit', {
        message: '',
        timer: null,
        show(text) {
            this.message = text;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => (this.message = ''), 5000);
            window.playInstrument?.('amadinda', 5);
        },
    });

    // Every save that can wait carries the practice time; its answer may say a task was done.
    const send = window.postJson;
    window.postJson = async (url, body = {}) => {
        const path = new URL(url, location.href).pathname;
        const isSave = SAVES.some((r) => r.test(path));
        if (isSave && body && body.active_seconds === undefined) body = { ...body, active_seconds: window.lugPracticeTake() };
        const data = await send(url, body);
        if (isSave && data && !data.queued) {
            announce(Alpine, data.habit);
            if (data.habit?.cowries_earned) window.updateCounters?.(data);
        }
        return data;
    };

    startClock(Alpine);

    // The toast sits just under the header, whose height changes with the screen width.
    const header = document.querySelector('body > header');
    if (header && 'ResizeObserver' in window) {
        new ResizeObserver(() => document.documentElement.style.setProperty('--lug-header', `${header.offsetHeight}px`)).observe(header);
    }

    /** Settings → Daily reminder. config = { key, on, hour, onUrl, offUrl } */
    Alpine.data('reminders', (config) => ({
        on: config.on,
        hour: config.hour ?? 18,
        busy: false,
        error: '',
        done: '',
        get supported() {
            return !!config.key && window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        },
        get blocked() { return 'Notification' in window && Notification.permission === 'denied'; },
        async subscription(create) {
            const reg = await navigator.serviceWorker.ready;
            const existing = await reg.pushManager.getSubscription();
            if (existing || !create) return existing;
            return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlKey(config.key) });
        },
        async enable() {
            this.busy = true;
            this.error = this.done = '';
            try {
                if ((await Notification.requestPermission()) !== 'granted') {
                    this.error = 'The browser said no to notifications. You can allow them in its site settings.';
                    return;
                }
                const sub = await this.subscription(true);
                await postJson(config.onUrl, { ...sub.toJSON(), hour: Number(this.hour), timezone: Intl.DateTimeFormat().resolvedOptions().timeZone });
                this.on = true;
                this.done = `Reminder set for ${label(this.hour)}.`;
            } catch (e) {
                this.error = "Couldn't switch the reminder on. Check your connection and try again.";
            } finally {
                this.busy = false;
            }
        },
        async disable() {
            this.busy = true;
            this.error = this.done = '';
            try {
                const sub = await this.subscription(false).catch(() => null);
                await postJson(config.offUrl, { endpoint: sub?.endpoint ?? null });
                await sub?.unsubscribe().catch(() => {});
                this.on = false;
                this.done = 'Reminders are off.';
            } catch (e) {
                this.error = "Couldn't switch the reminder off. Check your connection and try again.";
            } finally {
                this.busy = false;
            }
        },
        label,
    }));
};

function label(hour) {
    const h = Number(hour);
    return `${h % 12 || 12} ${h < 12 ? 'am' : 'pm'}`;
}

function urlKey(base64) {
    const pad = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}
