/**
 * Mukasa speaks his English lines (the speech bubble in lessons):
 *   - a real recording when an admin has made one (Admin → 🎙️ Mukasa's lines; the lesson page sets
 *     window.LUGANDA_TEACHER_VOICE = { line: address }),
 *   - otherwise the phone's built-in voice (speechSynthesis), a man's voice where the phone has one.
 * Learners can switch it off in Settings → Sound (kept on this device). Nothing is spoken while
 * voices are off for the site (LUGANDA_VOICE=false). While he speaks, the background music steps
 * aside and his mouth moves (it uses the same audio store as the recordings).
 */
const PREF = 'lug.teacher.voice'; // 'on' | 'off'
const SPEAKERS = ['mukasa'];

const get = (k, fallback) => { try { return localStorage.getItem(k) ?? fallback; } catch (e) { return fallback; } };
const set = (k, v) => { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } };
const tidy = (text) => String(text ?? '').replace(/\s+/g, ' ').trim();

/** A man's English voice if the phone has one; otherwise any English voice (spoken a little lower). */
let chosen;
function voice() {
    const synth = window.speechSynthesis;
    if (!synth) return null;
    if (chosen !== undefined && chosen !== null) return chosen;
    const all = synth.getVoices().filter((v) => /^en(-|_|$)/i.test(v.lang));
    if (!all.length) return null; // not loaded yet: try again next time
    const male = /\b(male|man|david|daniel|george|james|fred|guy|alex|mark|thomas|arthur|ryan|aaron|oliver|rishi|reed|eddy|ralph|lee|gordon)\b/i;
    const female = /\b(female|woman|zira|susan|samantha|karen|victoria|fiona|moira|tessa|hazel|serena|kate|libby|sonia|aria|jenny|catherine|allison|ava|nicky|martha|shelley|flo|sandy|grandma)\b/i;
    chosen = all.find((v) => male.test(v.name) && !female.test(v.name))
        ?? all.find((v) => !female.test(v.name) && /en-(gb|ke|ng|za|au|in)/i.test(v.lang))
        ?? all.find((v) => !female.test(v.name))
        ?? all[0];
    chosen.isMale = male.test(chosen.name);
    return chosen;
}
window.speechSynthesis?.addEventListener?.('voiceschanged', () => { chosen = undefined; voice(); });

let ticket = 0;
let playing = null; // the address of a recorded line that is playing

export default (Alpine) => {
    const store = {
        on: get(PREF, 'on') !== 'off',
        available: window.LUGANDA_VOICE !== false,
        setOn(on) {
            this.on = on;
            set(PREF, on ? 'on' : 'off');
            if (!on) stop();
        },
    };
    Alpine.store('teacherVoice', store);

    function audio() { return Alpine.store('audio'); }

    function stop() {
        ticket++;
        try { window.speechSynthesis?.cancel(); } catch (e) { /* ignore */ }
        if (audio()?.url === 'voice:teacher' || (playing && audio()?.url === playing)) audio().stop();
        playing = null;
    }

    /** The built-in voice; resolves when it has finished (or after a safety time). */
    function speak(text, mine) {
        return new Promise((resolve) => {
            const synth = window.speechSynthesis;
            if (!synth || !window.SpeechSynthesisUtterance) return resolve(false);
            const u = new SpeechSynthesisUtterance(text);
            const v = voice();
            if (v) { u.voice = v; u.lang = v.lang; } else { u.lang = 'en-GB'; }
            u.rate = 0.95;
            u.pitch = v?.isMale ? 1 : 0.8; // no man's voice on this phone: a lower one
            const a = audio();
            Object.assign(a, { url: 'voice:teacher', state: 'playing' }); // mouth moves, music steps aside
            let done = false;
            const finish = () => {
                if (done) return;
                done = true;
                clearTimeout(guard);
                if (mine() && a.url === 'voice:teacher') Object.assign(a, { url: null, state: 'idle' });
                resolve(true);
            };
            u.onend = finish;
            u.onerror = finish;
            const guard = setTimeout(finish, 2500 + text.length * 110); // some browsers forget to say "ended"
            try { synth.cancel(); synth.speak(u); } catch (e) { finish(); }
        });
    }

    window.lugTeacher = {
        /** Mukasa says this line. Resolves when he has finished (at once when he doesn't speak it). */
        async say(character, text) {
            const line = tidy(text);
            stop();
            if (!line || !SPEAKERS.includes(character) || !store.on || !store.available) return false;
            const mine = ((t) => () => t === ticket)(ticket);
            const recorded = (window.LUGANDA_TEACHER_VOICE || {})[line];
            if (recorded) {
                playing = recorded;
                await window.playAudioThen(recorded);
                if (playing === recorded) playing = null;
                return mine();
            }
            await speak(line, mine);
            return mine();
        },
        stop,
        /** Admin → Mukasa's lines: hear how the built-in voice reads a line. */
        preview(text) { stop(); const t = ticket; speak(tidy(text), () => t === ticket); },
    };

    /** Admin → Mukasa's lines: record one line in the browser and save it. */
    Alpine.data('lineRecorder', ({ text, url, save }) => ({
        text, saved: url, recording: false, take: null, blob: null, busy: false, error: '', message: '',
        rec: null, chunks: [],
        async toggle() {
            if (this.recording) return this.rec.stop();
            this.error = ''; this.message = '';
            try {
                stop();
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.chunks = [];
                this.rec = new MediaRecorder(stream);
                this.rec.ondataavailable = (e) => e.data.size && this.chunks.push(e.data);
                this.rec.onstop = () => {
                    stream.getTracks().forEach((t) => t.stop());
                    this.recording = false;
                    this.blob = new Blob(this.chunks, { type: this.rec.mimeType || 'audio/webm' });
                    this.take = URL.createObjectURL(this.blob);
                    window.dispatchEvent(new CustomEvent('lug-mic', { detail: false }));
                };
                this.rec.start();
                this.recording = true;
                window.dispatchEvent(new CustomEvent('lug-mic', { detail: true }));
                setTimeout(() => this.recording && this.rec.stop(), 20000); // a line is a few seconds
            } catch (e) {
                this.error = e?.name === 'NotAllowedError' ? 'The microphone is blocked: allow it in the browser.' : 'This browser can\'t record.';
            }
        },
        async upload() {
            if (!this.blob) return;
            this.busy = true; this.error = '';
            try {
                const type = this.blob.type || 'audio/webm';
                const ext = type.includes('ogg') ? 'ogg' : type.includes('mp4') || type.includes('aac') ? 'm4a' : 'webm';
                const form = new FormData();
                form.append('text', this.text);
                form.append('recording', this.blob, `take.${ext}`);
                const r = await fetch(save, { method: 'POST', body: form, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' } });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(data.errors?.recording?.[0] || data.message || 'Saving failed.');
                this.saved = data.url; this.take = null; this.blob = null;
                this.message = '✓ Saved';
            } catch (e) {
                this.error = e.message || 'Saving failed.';
            } finally {
                this.busy = false;
            }
        },
    }));
};
