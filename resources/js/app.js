import Alpine from 'alpinejs';
import lessonPlayer from './lesson-player';
import blogEditor from './blog-editor';
import teacherVoice from './teacher-voice';
import offline from './offline';
import music from './music';
import words from './words';
import habit from './habit';
import './pictures';

window.Alpine = Alpine;

/**
 * Recording playback: one recording at a time, reusing a single audio element
 * (kinder to phones). Alpine.store('audio') tells speaker buttons what is
 * happening: idle | loading | playing | error.
 */
let player;
let playToken = 0;
const audioStore = {
    url: null,
    state: 'idle',
    play(url, rate = 1) {
        if (!url || window.LUGANDA_VOICE === false) return false;
        this.stop();
        const token = ++playToken;
        player ??= new Audio();
        player.preload = 'auto';
        player.src = url;
        player.playbackRate = rate;
        player.preservesPitch = true;
        this.url = url;
        this.state = 'loading';
        const mine = () => token === playToken;
        player.onplaying = () => mine() && (this.state = 'playing');
        player.onended = () => mine() && Object.assign(this, { state: 'idle', url: null });
        player.onerror = () => mine() && (this.state = 'error');
        player.play().catch((e) => {
            if (!mine()) return;
            // Browsers block sound until the learner has tapped something: that is not a broken file.
            this.state = e?.name === 'NotAllowedError' || e?.name === 'AbortError' ? 'idle' : 'error';
        });
        return true;
    },
    stop() {
        playToken++;
        if (player) player.pause();
        this.url = null;
        this.state = 'idle';
    },
};
Alpine.store('audio', audioStore);

/** Plays a recording; returns false when no audio exists yet (or voices are off). */
window.playAudio = (url, rate = 1) => Alpine.store('audio').play(url, rate);

/** 🔊 / 🐢 buttons (components/play-button). */
Alpine.data('speaker', (url, slow = null) => ({
    url,
    slow,
    state(u) { return this.$store.audio.url === u ? this.$store.audio.state : 'idle'; },
    stateClass(u) { return `is-${this.state(u)}`; },
    play() { playAudio(this.url); },
    playSlow() { playAudio(this.slow); },
}));

/** Record-and-compare: learners hear themselves beside the native speaker. */
Alpine.data('recorder', () => ({
    recording: false,
    url: null,
    error: null,
    chunks: [],
    mediaRecorder: null,
    async toggle() {
        if (this.recording) {
            this.mediaRecorder.stop();
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.chunks = [];
            this.mediaRecorder = new MediaRecorder(stream);
            this.mediaRecorder.ondataavailable = (e) => this.chunks.push(e.data);
            this.mediaRecorder.onstop = () => {
                stream.getTracks().forEach((t) => t.stop());
                window.dispatchEvent(new CustomEvent('lug-mic', { detail: false }));
                if (this.url) URL.revokeObjectURL(this.url);
                this.url = URL.createObjectURL(new Blob(this.chunks, { type: this.mediaRecorder.mimeType }));
                this.recording = false;
            };
            window.dispatchEvent(new CustomEvent('lug-mic', { detail: true }));
            this.mediaRecorder.start();
            this.recording = true;
            this.error = null;
        } catch (e) {
            this.error = 'Microphone not available. Allow microphone access to practise speaking.';
        }
    },
    play() {
        if (this.url) new Audio(this.url).play();
    },
}));

/** Plays a recording and resolves when it has finished (or straight away if there is nothing to play). */
window.playAudioThen = (url, rate = 1) => new Promise((resolve) => {
    if (!playAudio(url, rate)) return resolve(false);
    const store = Alpine.store('audio');
    const wait = setInterval(() => {
        if (store.url !== url || store.state === 'error') {
            clearInterval(wait);
            resolve(true);
        }
    }, 100);
});

/**
 * "Say it" (speaking practice): hear the phrase, record yourself, compare the two,
 * then say whether it sounded the same. The verdict is saved as a speech attempt;
 * the recording itself stays in the browser, unless automatic checking is on
 * (window.LUGANDA_SPEAK.check), which sends it once to be checked and keeps nothing.
 *   x-data="sayIt({ phraseId, model, lessonId, source })"
 */
const SAY_MAX_MS = 7000; // a phrase takes a few seconds; stop by itself after this
Alpine.data('sayIt', (opts = {}) => ({
    phraseId: opts.phraseId,
    model: opts.model ?? null,
    lessonId: opts.lessonId ?? null,
    source: opts.source ?? 'learn',
    supported: !!(navigator.mediaDevices?.getUserMedia && window.MediaRecorder),
    checking: !!window.LUGANDA_SPEAK?.check,
    checker: window.LUGANDA_SPEAK?.checker ?? '',
    phase: 'ready',   // ready | recording | recorded
    url: null,
    blob: null,
    ms: 0,
    elapsed: 0,
    error: null,
    result: null,     // automatic check: { verdict, heard, score } or { message }
    busy: false,
    rated: null,      // good | again
    attemptId: null,
    attempts: 0,
    recorder: null,
    timer: null,

    get recording() { return this.phase === 'recording'; },
    get seconds() { return Math.ceil((SAY_MAX_MS - this.elapsed) / 1000); },
    get verdictText() {
        return {
            great: 'Very close!',
            close: 'Nearly there',
            again: 'Not quite yet',
            silent: "We couldn't hear any Luganda. Try again, a little closer to the microphone.",
        }[this.result?.verdict] ?? '';
    },

    async toggle() {
        if (this.recording) return this.stop();
        Alpine.store('audio').stop(); // never record the teacher's voice
        this.error = null;
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const chunks = [];
            this.recorder = new MediaRecorder(stream);
            this.recorder.ondataavailable = (e) => e.data.size && chunks.push(e.data);
            this.recorder.onstop = () => {
                stream.getTracks().forEach((t) => t.stop());
                window.dispatchEvent(new CustomEvent('lug-mic', { detail: false })); // background music may come back
                clearInterval(this.timer);
                if (this.url) URL.revokeObjectURL(this.url);
                this.blob = new Blob(chunks, { type: this.recorder.mimeType || 'audio/webm' });
                this.url = URL.createObjectURL(this.blob);
                this.ms = Math.min(SAY_MAX_MS, Date.now() - this.started);
                this.phase = 'recorded';
                this.attempts++;
                Object.assign(this, { rated: null, attemptId: null, result: null });
                if (this.checking) this.check();
            };
            this.started = Date.now();
            this.elapsed = 0;
            window.dispatchEvent(new CustomEvent('lug-mic', { detail: true })); // no background music in the recording
            this.recorder.start();
            this.phase = 'recording';
            this.timer = setInterval(() => {
                this.elapsed = Date.now() - this.started;
                if (this.elapsed >= SAY_MAX_MS) this.stop();
            }, 200);
        } catch (e) {
            this.error = 'Microphone not available. Allow the microphone to practise speaking, or just say it out loud.';
        }
    },
    stop() {
        if (this.recorder?.state === 'recording') this.recorder.stop();
    },
    playMine() { playAudio(this.url); },
    async compare() {
        if (this.model) {
            await playAudioThen(this.model);
            await new Promise((r) => setTimeout(r, 300));
        }
        playAudio(this.url);
    },

    /** The automatic check: send the recording once, show what was heard. */
    async check() {
        this.busy = true;
        try {
            const form = new FormData();
            form.append('audio', this.blob, 'attempt.' + (this.blob.type.includes('mp4') ? 'm4a' : this.blob.type.includes('ogg') ? 'ogg' : 'webm'));
            form.append('phrase_id', this.phraseId);
            form.append('source', this.source);
            form.append('duration_ms', this.ms);
            if (this.lessonId) form.append('lesson_id', this.lessonId);
            const res = await fetch(window.LUGANDA_SPEAK.check, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: form,
            });
            const data = await res.json().catch(() => ({}));
            this.attemptId = data.attempt_id ?? null;
            this.result = data.ok ? data : { message: data.message || "Couldn't check this one. Compare it yourself and choose below." };
        } catch (e) {
            this.result = { message: "Couldn't check this one. Compare it yourself and choose below." };
        } finally {
            this.busy = false;
        }
    },

    /** The learner's own verdict, saved as a speech attempt (not graded). */
    rate(rating) {
        this.rated = rating;
        this.$dispatch('said-it', { rating });
        postJson(window.LUGANDA_SPEAK.attempts, {
            phrase_id: this.phraseId, lesson_id: this.lessonId, source: this.source,
            self_rating: rating, duration_ms: this.ms || null, attempt_id: this.attemptId,
        }).then((d) => { this.attemptId = d.attempt_id; }).catch(() => {});
        if (rating === 'again') this.phase = 'ready';
    },
}));

/**
 * Kiganda sound kit, synthesised with the Web Audio API so no audio files are needed.
 *   engalabi - high, sharp hand drum      embuutu  - deep bass drum
 *   endongo  - plucked bowl lyre          amadinda - wooden xylophone
 *   ensaasi  - gourd shakers              endingidi - bowed tube fiddle
 * Older names used by the games: boom (embuutu), tak (rim), roll (engalabi flourish).
 */
let audioCtx;
const ctx = () => (audioCtx ??= new (window.AudioContext || window.webkitAudioContext)());
// Five-note scale in the spirit of Kiganda tuning (roughly equal steps).
const SCALE = [262, 300, 345, 396, 455, 524, 600, 690];

function tone({ type = 'sine', from, to = from, length, volume, when = 0, attack = 0.005, filter, out }) {
    const c = ctx();
    const t = c.currentTime + when;
    const osc = c.createOscillator();
    const gain = c.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(from, t);
    if (to !== from) osc.frequency.exponentialRampToValueAtTime(to, t + length);
    gain.gain.setValueAtTime(0.0001, t);
    gain.gain.exponentialRampToValueAtTime(volume, t + attack);
    gain.gain.exponentialRampToValueAtTime(0.0001, t + length);
    let node = osc.connect(gain);
    if (filter) {
        const f = c.createBiquadFilter();
        f.type = 'lowpass';
        f.frequency.value = filter;
        node = gain.connect(f);
    }
    node.connect(out ?? c.destination);
    osc.start(t);
    osc.stop(t + length + 0.05);
}

function noise({ length, volume, when = 0, band = 6000, out }) {
    const c = ctx();
    const t = c.currentTime + when;
    const buffer = c.createBuffer(1, c.sampleRate * length, c.sampleRate);
    const data = buffer.getChannelData(0);
    for (let i = 0; i < data.length; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / data.length);
    const src = c.createBufferSource();
    src.buffer = buffer;
    const f = c.createBiquadFilter();
    f.type = 'bandpass';
    f.frequency.value = band;
    const gain = c.createGain();
    gain.gain.value = volume;
    src.connect(f).connect(gain).connect(out ?? c.destination);
    src.start(t);
}

const instruments = {
    engalabi: (n = 0) => { tone({ from: 340 + n * 30, to: 160, length: 0.18, volume: 0.6 }); noise({ length: 0.04, volume: 0.25, band: 3000 }); },
    embuutu: () => { tone({ from: 120, to: 42, length: 0.7, volume: 1 }); noise({ length: 0.06, volume: 0.2, band: 400 }); },
    endongo: (n = 2) => {
        const f = SCALE[n % SCALE.length] / 2;
        tone({ type: 'sawtooth', from: f, length: 1.2, volume: 0.35, filter: 1400 });
        tone({ type: 'triangle', from: f * 2, length: 0.6, volume: 0.15 });
    },
    amadinda: (n = 0) => {
        const f = SCALE[n % SCALE.length];
        tone({ from: f, length: 0.35, volume: 0.55 });
        tone({ type: 'triangle', from: f * 4, length: 0.06, volume: 0.2 });
    },
    ensaasi: () => { noise({ length: 0.12, volume: 0.6, band: 7000 }); noise({ length: 0.1, volume: 0.45, band: 6000, when: 0.16 }); },
    endingidi: (n = 3) => {
        const f = SCALE[n % SCALE.length];
        tone({ type: 'sawtooth', from: f, to: f * 1.01, length: 0.9, volume: 0.22, attack: 0.15, filter: 2200 });
    },
    boom: () => instruments.embuutu(),
    tak: () => tone({ from: 420, to: 180, length: 0.08, volume: 0.4 }),
    roll: () => [0, 0.07, 0.14, 0.21].forEach((w, i) => setTimeout(() => instruments.engalabi(i), w * 1000)),
    // A small celebration: drum roll then a rising amadinda run.
    cheer: () => {
        instruments.roll();
        [0, 2, 4, 7].forEach((n, i) => setTimeout(() => instruments.amadinda(n), 300 + i * 110));
    },
};

window.playInstrument = (name, note) => {
    try {
        instruments[name]?.(note);
    } catch (e) {
        /* sound is a bonus; ignore browsers without Web Audio */
    }
};
window.drum = (kind = 'boom') => window.playInstrument(kind);

/**
 * Grooves (home page, "Sounds you'll hear"): tap an instrument and it keeps playing until it is
 * tapped again, or another instrument is tapped: its real recording on a loop when an admin has
 * uploaded one (Admin → Instrument sounds), otherwise its own Kiganda-style pattern.
 * Notes are scheduled a little ahead on the Web Audio clock, so the beat stays steady.
 * The background music pauses while a groove plays (event lug-groove, resources/js/music.js).
 */
const strokes = {
    // engalabi: deep "bass" in the middle of the skin, open tone at the edge, sharp slap
    bass: (w, out) => { tone({ from: 190, to: 85, length: 0.38, volume: 0.9, when: w, out }); noise({ length: 0.03, volume: 0.12, band: 500, when: w, out }); },
    open: (w, out) => { tone({ from: 330, to: 255, length: 0.22, volume: 0.6, when: w, out }); tone({ type: 'triangle', from: 660, to: 500, length: 0.08, volume: 0.12, when: w, out }); },
    slap: (w, out) => { noise({ length: 0.05, volume: 0.5, band: 2600, when: w, out }); tone({ from: 560, to: 320, length: 0.07, volume: 0.3, when: w, out }); },
    ghost: (w, out) => noise({ length: 0.025, volume: 0.15, band: 3200, when: w, out }),
    // embuutu: big deep drum, and a rim knock
    boom: (w, out, v = 1) => { tone({ from: 105, to: 40, length: 0.85, volume: v, when: w, out }); noise({ length: 0.06, volume: 0.18 * v, band: 300, when: w, out }); },
    rim: (w, out) => { tone({ from: 700, to: 420, length: 0.05, volume: 0.25, when: w, out }); noise({ length: 0.02, volume: 0.2, band: 4000, when: w, out }); },
    // amadinda: a wooden key, struck (pitched bar plus a short knock)
    key: (n, w, out, v = 0.5) => {
        const f = SCALE[((n % SCALE.length) + SCALE.length) % SCALE.length];
        tone({ from: f, length: 0.32, volume: v, when: w, out });
        tone({ type: 'triangle', from: f * 3.9, length: 0.05, volume: v * 0.35, when: w, out });
        noise({ length: 0.015, volume: v * 0.3, band: 1800, when: w, out });
    },
    // endongo: a plucked string with a soft body
    pluck: (n, w, out, v = 0.3) => {
        const f = SCALE[((n % SCALE.length) + SCALE.length) % SCALE.length] / 2;
        tone({ type: 'sawtooth', from: f, length: 1.1, volume: v, when: w, filter: 1300, out });
        tone({ type: 'triangle', from: f * 2, length: 0.4, volume: v * 0.4, when: w, out });
    },
    // ensaasi: a gourd shaken forward (long) or back (short)
    shake: (w, out, v = 0.5, len = 0.1) => noise({ length: len, volume: v, band: 7200, when: w, out }),
};
const GROOVES = {
    engalabi: { step: 0.15, play(i, w, out) {
        const p = ['bass', 'ghost', 'slap', 'open', 'ghost', 'slap', 'bass', 'bass', 'slap', 'open', 'slap', 'ghost'][i % 12];
        strokes[p](w, out);
    } },
    embuutu: { step: 0.2, play(i, w, out) {
        const k = i % 12;
        if ([0, 3, 6, 8].includes(k)) strokes.boom(w, out, k === 0 ? 1 : 0.75);
        else if ([5, 10].includes(k)) strokes.rim(w, out);
    } },
    endongo: { step: 0.24, play(i, w, out) {
        const melody = [0, 2, 4, 2, 5, 4, 2, 1, 0, 2, 4, 5, 7, 5, 4, 2];
        strokes.pluck(melody[i % 16] + 2, w, out, i % 4 === 0 ? 0.32 : 0.24);
        if (i % 8 === 0) strokes.pluck(0, w, out, 0.22); // the low drone string
    } },
    amadinda: { step: 0.11, play(i, w, out) {
        // two players interlocking: one on the even beats, one on the odd ones
        const first = [4, 2, 5, 3, 1, 4, 2, 0, 3, 5, 1, 2];
        const second = [1, 4, 0, 3, 5, 2, 4, 1, 0, 3, 2, 5];
        const k = Math.floor(i / 2) % 12;
        strokes.key(i % 2 === 0 ? first[k] : second[k] + 1, w, out, i % 2 === 0 ? 0.5 : 0.4);
    } },
    ensaasi: { step: 0.14, play(i, w, out) {
        const k = i % 8;
        if ([0, 3, 6].includes(k)) strokes.shake(w, out, 0.55, 0.13); // 3 + 3 + 2
        else strokes.shake(w, out, 0.22, 0.06);
    } },
};
let groove = null;
const recordings = {}; // url => decoded AudioBuffer (or the promise of one)
function stopGroove() {
    if (!groove) return;
    clearInterval(groove.timer);
    const { bus, source } = groove;
    try { bus.gain.setTargetAtTime(0, ctx().currentTime, 0.04); } catch (e) { /* ignore */ }
    setTimeout(() => { try { source?.stop(); } catch (e) { /* already stopped */ } bus.disconnect(); }, 400);
    groove = null;
    window.dispatchEvent(new CustomEvent('lug-groove', { detail: false }));
}
/** The built-in rhythm: notes scheduled a little ahead on the Web Audio clock, so the beat stays steady. */
function playPattern(g, pattern) {
    const c = ctx();
    let i = 0;
    let next = c.currentTime + 0.06;
    g.timer = setInterval(() => {
        while (next < c.currentTime + 0.15) {
            pattern.play(i++, Math.max(0, next - c.currentTime), g.bus);
            next += pattern.step;
        }
    }, 25);
}
/** A real recording (Admin → Instrument sounds), looped without a gap until it is stopped. */
async function playRecording(g, url, pattern) {
    const c = ctx();
    try {
        recordings[url] ??= fetch(url).then((r) => { if (!r.ok) throw new Error(r.status); return r.arrayBuffer(); })
            .then((data) => new Promise((ok, fail) => c.decodeAudioData(data, ok, fail)));
        const buffer = await recordings[url];
        if (groove !== g) return; // tapped again (or another one) while it was loading
        const source = c.createBufferSource();
        source.buffer = buffer;
        source.loop = true;
        source.connect(g.bus);
        source.start();
        g.source = source;
    } catch (e) {
        delete recordings[url];
        if (groove === g) playPattern(g, pattern); // couldn't load it: play the built-in rhythm instead
    }
}
function startGroove(name, url = null) {
    stopGroove();
    const pattern = GROOVES[name];
    if (!pattern) return null;
    const c = ctx();
    c.resume?.();
    const bus = c.createGain();
    bus.gain.value = 0.9;
    bus.connect(c.destination);
    groove = { name, bus, timer: null, source: null };
    if (url) playRecording(groove, url, pattern);
    else playPattern(groove, pattern);
    window.dispatchEvent(new CustomEvent('lug-groove', { detail: true }));
    return name;
}
window.lugGroove = {
    /** Start this instrument (its recording if there is one, else its rhythm), or stop it if it is already playing. Returns what is playing now. */
    toggle(name, url = null) {
        try {
            return groove?.name === name ? (stopGroove(), null) : startGroove(name, url);
        } catch (e) {
            return null; // no Web Audio: the instruments just stay quiet
        }
    },
    stop: stopGroove,
    get playing() { return groove?.name ?? null; },
};
document.addEventListener('visibilitychange', () => { if (document.hidden) stopGroove(); });
window.addEventListener('pagehide', stopGroove);

/**
 * "Talking" sound for the animated characters when no recording exists yet:
 * one soft note per syllable, lower for Mukasa, higher for Nakato.
 * Returns how long it lasts in milliseconds.
 */
window.babble = (text, who = 0) => {
    const syllables = Math.max(1, (text.match(/[aeiou]+/gi) || []).length);
    if (window.LUGANDA_VOICE === false) return syllables * 150 + 250; // voices off: mouth moves, no sound
    const base = who === 0 ? 170 : 290;
    try {
        for (let i = 0; i < syllables; i++) {
            const f = base * [1, 1.12, 1.25, 1.12, 0.9][i % 5] * (i === syllables - 1 && text.trim().endsWith('?') ? 1.3 : 1);
            tone({ type: 'triangle', from: f, to: f * 0.92, length: 0.11, volume: 0.18, when: i * 0.15 });
        }
    } catch (e) {
        /* silent if Web Audio is unavailable */
    }
    return syllables * 150 + 250;
};

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

/** Plays a recording and resolves when it ends (or after a safety timeout). */
const playAndWait = (url) => new Promise((resolve) => {
    Alpine.store('audio').stop(); // never talk over a speaker button
    const audio = new Audio(url);
    const done = () => resolve();
    audio.addEventListener('ended', done, { once: true });
    audio.addEventListener('error', done, { once: true });
    audio.play().catch(done);
    setTimeout(done, 6000);
});

/** How long a line takes when it is shown without sound (bubble + moving mouth). */
const silentLength = (text) => Math.max(1, (text.match(/[aeiou]+/gi) || []).length) * 150 + 250;

/**
 * Two characters taking turns to speak (see components/talk-scene).
 * lines: [{ who: 0|1, luganda, english, audio }]
 * options.autoplaySound: false = when the scene starts by itself (e.g. scrolled into view on
 * the home page) it plays silently; sound only comes when someone taps a character or "Hear it".
 */
const makeTalkScene = (lines = [], options = {}) => ({
    lines,
    said: {},          // the latest line each character has said: { 0: line, 1: line }
    taps: {},          // how many times each character has been tapped (to cycle their lines)
    speaking: null,
    reactions: {},     // a passing mood per character, e.g. { 1: 'thinking' }
    playing: false,
    showMeaning: options.showMeaning ?? true,
    run: 0,
    key: undefined,
    init() {
        if (options.autoplay === false) return;
        // Start the conversation when it scrolls into view.
        const io = new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                io.disconnect();
                this.play({ sound: options.autoplaySound ?? true });
            }
        }, { threshold: 0.4 });
        io.observe(this.$el);
    },
    /**
     * Keep the scene in step with a changing conversation (e.g. the next lesson step).
     * The first call just records the key; later changes swap lines and replay.
     */
    sync(key, next) {
        if (this.key === undefined) {
            this.key = key;
            return;
        }
        if (key === this.key) return;
        this.key = key;
        this.setLines(next);
        this.$nextTick(() => this.play({ sound: options.autoplaySound ?? true }));
    },
    setLines(next) {
        this.lines = next;
        this.said = {};
        this.taps = {};
        this.speaking = null;
        this.run++;
        this.playing = false;
    },
    bubbleFor(who) {
        return this.said[who] ?? null;
    },
    /** The character's mood (components/character): speaking, a reaction, or idle. */
    sceneState(who) {
        if (this.speaking === who) return 'speaking';
        return this.reactions[who] ?? 'idle';
    },
    react(who, mood, ms = 1000) {
        this.reactions = { ...this.reactions, [who]: mood };
        setTimeout(() => {
            if (this.reactions[who] === mood) this.reactions = { ...this.reactions, [who]: null };
        }, ms);
    },
    /** One character says one line, with their voice (or the placeholder sound). */
    async speak(line, run, sound = true) {
        this.said = { ...this.said, [line.who]: line };
        this.speaking = line.who;
        if (!sound) await wait(silentLength(line.luganda));
        else if (line.audio) await playAndWait(line.audio);
        else await wait(window.babble(line.luganda, line.who));
        if (run === this.run) this.speaking = null;
    },
    /** Tap a character: they say their line. Tap again for their next line. */
    async say(who) {
        const mine = this.lines.filter((l) => l.who === who);
        if (!mine.length) return;
        const n = (this.taps[who] ?? -1) + 1;
        this.taps = { ...this.taps, [who]: n };
        const run = ++this.run;      // interrupts a conversation that is playing
        this.playing = false;
        await this.speak(mine[n % mine.length], run);
    },
    /** The whole conversation, turn by turn ({ sound: false } = bubbles and mouths only). */
    async play({ sound = true } = {}) {
        if (this.playing || !this.lines.length) return;
        const run = ++this.run;
        this.playing = true;
        this.said = {};
        for (const line of this.lines) {
            if (run !== this.run) return;
            await this.speak(line, run, sound);
            if (run !== this.run) return;
            await wait(450);
        }
        if (run !== this.run) return;
        this.playing = false;
        this.$dispatch('talk-done');
    },
});

Alpine.data('talkScene', makeTalkScene);

/**
 * "Your turn": the learner plays one character. The other character's lines
 * play on their own; at each of the learner's lines, they pick the reply.
 * step = { you, lines, options: { lineIndex: [choices] } }
 * Dispatches `converse-done` with { correct } (correct = no wrong picks).
 */
Alpine.data('converseStep', (step) => ({
    ...makeTalkScene(step.lines, { autoplay: false }),
    convo: step, // (not called "step": that name must keep meaning the lesson's current step)
    you: step.you,
    turn: 0,
    waiting: false,
    wrong: [],
    mistakes: 0,
    finished: false,
    init() {
        // Report back from the step itself: after an answer, `this.$el` is the tapped button,
        // which may already have left the page (then the event would never reach the lesson).
        this.root = this.$el;
        setTimeout(() => this.proceed(), 400);
    },
    sync(key, next) {
        if (this.key === undefined) {
            this.key = key;
            return;
        }
        if (key === this.key) return;
        this.key = key;
        Object.assign(this, { convo: next, you: next.you, turn: 0, waiting: false, wrong: [], mistakes: 0, finished: false, reactions: {} });
        this.setLines(next.lines);
        setTimeout(() => this.proceed(), 400);
    },
    get yourName() { return this.you === 0 ? 'Mukasa' : 'Nakato'; },
    get partnerName() { return this.you === 0 ? 'Nakato' : 'Mukasa'; },
    get choices() { return this.convo.options[this.turn] ?? []; },
    /** Play the other character's lines until it's the learner's turn. */
    async proceed() {
        const run = this.run;
        while (this.turn < this.lines.length) {
            const line = this.lines[this.turn];
            if (line.who === this.you) {
                this.waiting = true;
                this.wrong = [];
                this.said = { ...this.said, [this.you]: { luganda: '…?', english: 'Your turn' } };
                return;
            }
            await this.speak(line, run);
            if (run !== this.run) return;
            await wait(350);
            this.turn++;
        }
        this.finished = true;
        this.waiting = false;
        this.react(0, 'happy', 1600);
        this.react(1, 'happy', 1600);
        this.root.dispatchEvent(new CustomEvent('converse-done', { detail: { correct: this.mistakes === 0 }, bubbles: true }));
    },
    async choose(option) {
        if (!this.waiting || this.wrong.includes(option)) return;
        const line = this.lines[this.turn];
        if (option !== line.luganda) {
            this.mistakes++;
            this.wrong.push(option);
            this.react(this.you === 0 ? 1 : 0, 'thinking', 1200); // the partner looks puzzled
            playInstrument('tak');
            return;
        }
        this.waiting = false;
        this.react(this.you === 0 ? 1 : 0, 'happy', 1200);
        playInstrument('amadinda', 3);
        await this.speak(line, this.run);
        await wait(350);
        this.turn++;
        this.proceed();
    },
    /** Replay only what has been said so far (never give away the answer). */
    async play() {
        if (this.playing || (!this.waiting && !this.finished)) return; // let the partner finish first
        const run = ++this.run;
        this.playing = true;
        for (let i = 0; i < this.turn && i < this.lines.length; i++) {
            if (run !== this.run) return;
            await this.speak(this.lines[i], run);
            await wait(350);
        }
        this.playing = false;
        if (this.waiting) this.said = { ...this.said, [this.you]: { luganda: '…?', english: 'Your turn' } };
    },
    async say(who) {
        if (!this.waiting && !this.finished) return; // let the partner finish first
        const heard = this.lines.slice(0, this.turn).filter((l) => l.who === who);
        if (!heard.length) return;
        const n = (this.taps[who] ?? -1) + 1;
        this.taps = { ...this.taps, [who]: n };
        await this.speak(heard[n % heard.length], ++this.run);
    },
    yourLines() { return this.lines.filter((l) => l.who === this.you); },
}));

// window.postJson (with the offline queue) lives in ./offline.js

/** Keep the header's cowrie and streak counters in sync after earning. */
window.updateCounters = (data) => {
    if (!data || data.queued) return;
    if (data?.cowries !== undefined) document.querySelector('[data-cowries]').textContent = data.cowries;
    if (data?.streak !== undefined) document.querySelector('[data-streak]').textContent = data.streak;
};

offline(Alpine);
music(Alpine);
words(Alpine);
habit(Alpine);
lessonPlayer(Alpine);
blogEditor(Alpine);
teacherVoice(Alpine);

Alpine.start();
