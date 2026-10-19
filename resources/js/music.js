/**
 * Background music: "Mpola" (gently), an original slow piece in a Kiganda style
 * (public/music/, made for this app). Quiet, and never in the way:
 *
 *   - On by default; the 🎵 button in the header (and Settings) turns it off, remembered on this device.
 *   - Browsers only allow sound after a tap, so it fades in on the learner's first tap or key.
 *   - It turns down while a Luganda recording plays, and stops while the microphone records.
 *   - It pauses when the app is in the background, and carries on from the same place on the next page.
 *   - The layout sets window.LUGANDA_MUSIC to the file's address, or null where there is no music
 *     (admin, Check, Studio, or LUGANDA_MUSIC=false in .env).
 */
const PREF = 'lug.music';          // 'on' | 'off'
const LEVEL = 'lug.music.level';   // 'quiet' | 'medium'
const AT = 'lug.music.at';         // where it got to (this tab only)
const VOLUMES = { quiet: 0.12, medium: 0.25 };
const DUCKED = 0.025;              // while a recording plays

const get = (k, fallback) => { try { return localStorage.getItem(k) ?? fallback; } catch (e) { return fallback; } };
const set = (k, v) => { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } };

export default (Alpine) => {
    const src = window.LUGANDA_MUSIC || null;
    let el = null;
    let ac = null;
    let gain = null;
    let holds = 0; // the microphone is recording
    let started = false;

    const store = {
        available: !!src,
        on: get(PREF, 'on') !== 'off',
        level: get(LEVEL, 'quiet') in VOLUMES ? get(LEVEL, 'quiet') : 'quiet',
        playing: false,
        toggle() { this.setOn(!this.on); },
        setOn(on) {
            this.on = on;
            set(PREF, on ? 'on' : 'off');
            on ? start() : fadeTo(0, 0.6, true);
        },
        /** What is happening right now (for checking): volume, position, paused. */
        now() { return { volume: gain ? gain.gain.value : el?.volume ?? 0, at: el?.currentTime ?? 0, paused: el ? el.paused : true }; },
        setLevel(level) {
            if (!(level in VOLUMES)) return;
            this.level = level;
            set(LEVEL, level);
            apply();
        },
    };
    Alpine.store('music', store);
    if (!src) return;

    function target() {
        if (!store.on || holds || document.hidden) return 0;
        const speaking = ['loading', 'playing'].includes(Alpine.store('audio')?.state);
        return speaking ? DUCKED : VOLUMES[store.level];
    }

    function setup() {
        if (el) return;
        el = new Audio();
        el.src = src;
        el.loop = true;
        el.preload = 'auto';
        // Carry on where the last page got to (set once the file's length is known).
        let at = 0;
        try { at = parseFloat(sessionStorage.getItem(AT)) || 0; } catch (e) { /* nothing saved */ }
        if (at > 0) el.addEventListener('loadedmetadata', () => { if (at < el.duration) el.currentTime = at; }, { once: true });
        // Web Audio for the volume: iPhones ignore an audio element's own volume.
        try {
            ac = new (window.AudioContext || window.webkitAudioContext)();
            gain = ac.createGain();
            gain.gain.value = 0;
            ac.createMediaElementSource(el).connect(gain).connect(ac.destination);
        } catch (e) {
            ac = null;
            el.volume = 0;
        }
    }

    function fadeTo(volume, seconds, thenPause = false) {
        if (!el) return;
        if (gain && ac) {
            const g = gain.gain;
            const now = ac.currentTime;
            g.cancelScheduledValues(now);
            g.setValueAtTime(g.value, now);
            g.linearRampToValueAtTime(volume, now + seconds);
        } else {
            el.volume = volume;
        }
        clearTimeout(fadeTo.timer);
        if (thenPause && volume === 0) {
            fadeTo.timer = setTimeout(() => { el.pause(); store.playing = false; }, seconds * 1000 + 50);
        }
    }

    async function start() {
        if (!store.on || document.hidden) return;
        setup();
        try {
            if (ac?.state === 'suspended') await ac.resume();
            await el.play();
            store.playing = true;
            started = true;
            fadeTo(target(), 2.5);
        } catch (e) {
            // Not allowed yet (no tap so far): the first tap starts it. Interrupted (e.g. by moving to
            // where the last page got to): try once more when it's ready.
            if (e?.name !== 'NotAllowedError' && !start.retried) {
                start.retried = true;
                el.addEventListener('canplay', () => start(), { once: true });
            }
        }
    }

    function apply(seconds = 0.4) {
        if (!started) return;
        const v = target();
        if (v > 0 && el.paused) return start();
        fadeTo(v, seconds, v === 0);
    }

    // The first tap or key anywhere starts it (browsers' rule for sound).
    const firstTouch = () => {
        ['pointerdown', 'keydown'].forEach((t) => window.removeEventListener(t, firstTouch, true));
        if (!started) start();
    };
    ['pointerdown', 'keydown'].forEach((t) => window.addEventListener(t, firstTouch, true));

    document.addEventListener('alpine:initialized', () => {
        start(); // allowed straight away on some browsers once the learner has used the site
        // Duck under the Luganda recordings.
        Alpine.effect(() => {
            Alpine.store('audio').state; // re-run when it changes
            apply(0.3);
        });
    });

    // The microphone: no music in the learner's recording.
    window.addEventListener('lug-mic', (e) => {
        holds = Math.max(0, holds + (e.detail ? 1 : -1));
        apply(0.15);
    });

    document.addEventListener('visibilitychange', () => apply(document.hidden ? 0.3 : 1.5));
    window.addEventListener('pagehide', () => {
        try { if (el && !el.paused) sessionStorage.setItem(AT, String(el.currentTime)); } catch (e) { /* ignore */ }
    });
};
