/**
 * The lesson player (resources/views/lesson.blade.php). It walks through the steps
 * built by App\Services\LessonPlan; each step type has its own view in
 * resources/views/lesson/steps/.
 *
 *   not graded:   learn, speak, intro, dialogue, reward, pattern → "Continue"
 *   pick & check: choose, recall, listen, sequence, find, build
 *   self-graded:  converse, match, sort (they report back with an event)
 *
 * Missed questions come back once at the end. Optional per-step extras:
 * step.feedback = { correct, wrong }, step.prompt, step.say, step.picture.
 */
const UNGRADED = ['learn', 'speak', 'intro', 'dialogue', 'reward', 'pattern'];
const SELF_GRADED = ['converse', 'match', 'sort'];
/** Conversations show both characters themselves, so the classroom teacher steps aside. */
const NO_TEACHER = ['dialogue', 'converse'];
const NAMES = { mukasa: 'Mukasa', nakato: 'Nakato', ngoma: 'Ngoma' };
/** What the teacher says on each kind of step (a step's own "say" wins). */
const TEACHER_SAYS = {
    learn: 'Listen, then say it with me.',
    speak: 'Your turn to speak! Listen, then say it.',
    choose: 'What does this mean?',
    recall: 'How do you say this in Luganda?',
    listen: 'Listen carefully. What did I say?',
    build: 'Put the words in the right order.',
    match: 'Match each word with its meaning.',
    sequence: 'What comes next?',
    find: 'Find the right picture.',
    pattern: 'Look at how the words change.',
    sort: 'Put each word in its basket.',
    reward: 'Kulika! Well done!',
};
const ENCOURAGE = ["Not quite. That's how we learn!", 'Almost! Look at the answer below.', "Don't worry, it will come back later."];
/** How long a line takes to "say" when it has no recording (mouth moves, no sound). */
const sayingTime = (text = '') => Math.min(4000, Math.max(900, (text.match(/[aeiou]+/gi) || []).length * 160 + 400));

export default (Alpine) => {
    Alpine.data('lessonPlayer', (steps, completeUrl, teacher = 'mukasa') => ({
        lessonId: null,   // speaking practice saves attempts against the lesson (data-lesson)
        queue: steps.map((s) => ({ ...s })),
        index: 0,
        graded: 0,
        firstTryCorrect: 0,
        selected: null,
        checked: false,
        correct: false,
        tiles: [],
        picked: [],
        counted: 0,       // count-along: how many pictures have been tapped
        finished: false,
        result: null,
        saveError: false,
        savedOffline: false,
        praise: '',
        streak: 0,
        prompts: { choose: 'What does this mean?', recall: 'How do you say this in Luganda?', listen: 'What did you hear?', sequence: 'What comes next?' },
        praises: ['Kirungi! Good!', 'Weewaawo! Exactly!', 'Ggwe! Well done!', 'Bulungi nnyo! Very good!'],
        encourage: '',
        mood: null,       // a passing mood: speaking | correct | wrong | happy | sad
        moodTimer: null,
        teacherDefault: teacher, // Mukasa or Nakato, taking turns by stage (LessonController)

        // ---- the teacher ----------------------------------------------------
        get teacher() { return this.step?.character || this.teacherDefault; },
        get teacherLabel() { return NAMES[this.teacher] + ' says'; },
        get showTeacher() { return !NO_TEACHER.includes(this.step?.type); },
        /** The teacher's mood right now. */
        get teacherState() {
            if (this.mood) return this.mood;
            if (this.$store.audio.state === 'playing') return 'speaking';
            if (this.checked) return this.correct ? 'happy' : 'sad';
            return {
                intro: 'happy', learn: 'pointing', speak: 'listening', reward: 'celebrating',
                choose: 'listening', recall: 'listening', listen: 'listening', sequence: 'listening', find: 'listening', build: 'thinking', match: 'listening',
                pattern: 'pointing', sort: 'listening',
            }[this.step?.type] ?? 'idle';
        },
        /** What is in the teacher's speech bubble. */
        get bubble() {
            const s = this.step;
            if (!s) return { text: '' };
            if (this.checked) return { text: this.correct ? this.praise : this.encourage };
            if (s.type === 'intro') return { text: s.say, luganda: s.phrase?.luganda, audio: s.phrase?.audio };
            return { text: s.say || s.prompt || TEACHER_SAYS[s.type] || '' };
        },
        /** Show a mood for a while, then go back to following the lesson. */
        flash(mood, ms) {
            clearTimeout(this.moodTimer);
            this.mood = mood;
            this.moodTimer = setTimeout(() => (this.mood = null), ms);
        },

        init() {
            this.lessonId = Number(this.$el.dataset.lesson) || null;
            // Keep this lesson's recordings on the phone, so it also works without a signal later.
            const audio = [];
            JSON.stringify(steps, (k, v) => { if ((k === 'audio' || k === 'audio_slow') && v) audio.push(v); return v; });
            window.lugKeep?.(audio);
            this.prepare();
        },
        get step() { return this.queue[this.index] ?? null; },
        get progress() { return this.queue.length ? Math.round((this.index / this.queue.length) * 100) : 0; },
        get score() { return this.graded ? Math.round((this.firstTryCorrect / this.graded) * 100) : 100; },
        get ungraded() { return UNGRADED.includes(this.step?.type); },
        get canCheck() {
            if (!this.step) return false;
            if (this.ungraded || this.checked) return true;
            if (SELF_GRADED.includes(this.step.type)) return false; // answered inside the step
            if (this.step.type === 'build') return this.picked.length > 0;
            return this.selected !== null;
        },
        get buttonLabel() {
            if (this.ungraded || this.checked) return 'Continue';
            return { converse: 'Your turn ↑', match: 'Match them ↑', sort: 'Sort them ↑' }[this.step?.type] ?? 'Check';
        },
        get wrongText() {
            return { converse: 'Good effort! The replies were:', match: 'Good effort! The pairs are:', sort: 'Good effort! They go like this:' }[this.step?.type] ?? 'Not quite. The answer is:';
        },

        prepare() {
            this.selected = null;
            this.checked = false;
            this.picked = [];
            this.counted = 0;
            this.tiles = (this.step?.tiles ?? []).map((text) => ({ text, used: false }));
            const type = this.step?.type;
            // The teacher "says" the line (lips move even when there is no recording to play).
            this.mood = null;
            if (this.showTeacher && this.bubble.text) this.flash('speaking', sayingTime(this.bubble.text));
            if (type === 'listen' || type === 'learn' || type === 'speak') this.$nextTick(() => playAudio(this.step.phrase.audio));
            if (type === 'intro' && this.step.phrase?.audio) this.$nextTick(() => playAudio(this.step.phrase.audio));
            if (type === 'reward') playInstrument('cheer');
        },
        /** Count-along: tap the next picture, hear a note, see its number. */
        countTap(event) {
            const items = [...event.currentTarget.querySelectorAll('.lug-pic-item')];
            if (!this.step?.count_along || this.counted >= items.length) return;
            const item = items[this.counted];
            this.counted++;
            item.classList.add('is-counted');
            item.dataset.n = this.counted;
            playInstrument('amadinda', this.counted);
            // After the last one, say the number. Take the recording now: the learner may already be on the next step.
            const word = this.step.phrase?.audio;
            const at = this.index;
            if (this.counted === items.length && word) setTimeout(() => this.index === at && playAudio(word), 350);
        },
        pick(i) { this.tiles[i].used = true; this.picked.push({ text: this.tiles[i].text, from: i }); },
        unpick(i) { const [t] = this.picked.splice(i, 1); this.tiles[t.from].used = false; },
        optionClass(option) {
            if (!this.checked) return this.selected === option ? 'border-bark-500 bg-bark-50' : 'border-bark-100 bg-white hover:border-bark-500';
            if (option === this.step.answer) return 'border-leaf-500 bg-leaf-100';
            if (option === this.selected) return 'border-red-400 bg-red-50';
            return 'border-bark-100 bg-white opacity-60';
        },

        advance() {
            if (this.ungraded || this.checked) return this.next();
            if (SELF_GRADED.includes(this.step.type)) return;
            const given = this.step.type === 'build' ? this.picked.map((t) => t.text).join(' ') : this.selected;
            this.grade(given === this.step.answer);
        },
        /** Conversations and matching games report back when the learner is done. */
        selfGraded({ correct }, type) {
            if (this.step?.type !== type || this.checked) return;
            this.grade(correct, false);
        },
        converseDone(detail) { this.selfGraded(detail, 'converse'); },
        matchDone(detail) { this.selfGraded(detail, 'match'); },
        sortDone(detail) { this.selfGraded(detail, 'sort'); },
        grade(correct, echo = true) {
            this.correct = correct;
            this.checked = true;
            if (!this.step.retry) {
                this.graded++;
                if (this.correct) this.firstTryCorrect++;
            }
            if (this.correct) {
                this.streak++;
                this.praise = this.step.feedback?.correct || this.praises[Math.floor(Math.random() * this.praises.length)];
                this.flash(this.streak % 3 === 0 ? 'celebrating' : 'correct', 1600);
                playInstrument(this.streak % 3 === 0 ? 'cheer' : 'amadinda', this.streak);
                const echoAudio = this.step.type === 'sequence' ? this.step.next?.audio : this.step.phrase?.audio;
                if (echo && echoAudio) setTimeout(() => playAudio(echoAudio), 250);
            } else {
                this.streak = 0;
                this.encourage = this.step.feedback?.wrong || ENCOURAGE[Math.floor(Math.random() * ENCOURAGE.length)];
                this.flash('wrong', 1100);
                playInstrument('tak');
                // Missed questions come back once at the end of the lesson.
                this.queue.push({ ...this.step, retry: true });
            }
        },
        next() {
            if (this.step?.type === 'learn') playInstrument('engalabi');
            this.index++;
            if (this.index >= this.queue.length) return this.finish();
            this.prepare();
        },
        async finish() {
            this.finished = true;
            playInstrument('cheer');
            try {
                const r = await postJson(completeUrl, { score: this.score });
                if (r.queued) {
                    this.savedOffline = true; // sent (and rewarded) when the phone is back online
                } else {
                    this.result = r;
                    updateCounters(r);
                }
            } catch (e) {
                this.saveError = true;
            }
        },
    }));

    /**
     * Match: tap a Luganda word, then its meaning. Wrong taps wiggle; when every
     * pair is found it dispatches `match-done` with { correct } (no wrong taps).
     */
    Alpine.data('matchStep', (step) => ({
        pairs: step.pairs,
        meanings: step.meanings,
        chosen: null,
        found: [],
        wrongTap: null,
        mistakes: 0,
        key: undefined,
        init() { this.root = this.$el; }, // report back from the step, not from the tapped button
        sync(key, next) {
            if (this.key === undefined) { this.key = key; return; }
            if (key === this.key) return;
            Object.assign(this, { key, pairs: next.pairs, meanings: next.meanings, chosen: null, found: [], wrongTap: null, mistakes: 0 });
        },
        isFound(english) { return this.found.includes(english); },
        pickWord(pair) {
            if (this.isFound(pair.english)) return;
            this.chosen = pair;
            if (pair.audio) playAudio(pair.audio);
        },
        pickMeaning(english) {
            if (!this.chosen || this.isFound(english)) return;
            if (english === this.chosen.english) {
                this.found.push(english);
                this.chosen = null;
                playInstrument('amadinda', this.found.length);
                if (this.found.length === this.pairs.length) {
                    setTimeout(() => this.root.dispatchEvent(new CustomEvent('match-done', { detail: { correct: this.mistakes === 0 }, bubbles: true })), 300);
                }
            } else {
                this.mistakes++;
                this.wrongTap = english;
                playInstrument('tak');
                setTimeout(() => (this.wrongTap = null), 500);
            }
        },
    }));

    /**
     * Sort (Phase 16): tap a word, then its basket. A wrong basket wiggles; when every
     * word is in place it dispatches `sort-done` with { correct } (no wrong baskets).
     */
    Alpine.data('sortStep', (step) => ({
        bins: step.bins,
        items: step.items,
        placed: {},
        chosen: null,
        wrongBin: null,
        mistakes: 0,
        key: undefined,
        init() { this.root = this.$el; },
        sync(key, next) {
            if (this.key === undefined) { this.key = key; return; }
            if (key === this.key) return;
            Object.assign(this, { key, bins: next.bins, items: next.items, placed: {}, chosen: null, wrongBin: null, mistakes: 0 });
        },
        get placedCount() { return Object.keys(this.placed).length; },
        isPlaced(item) { return item.id in this.placed; },
        placedIn(bin) { return this.items.filter((i) => this.placed[i.id] === bin); },
        pick(item) {
            if (this.isPlaced(item)) return;
            this.chosen = item;
            if (item.audio) playAudio(item.audio);
        },
        drop(bin) {
            if (!this.chosen) return;
            if (this.chosen.bin === bin) {
                this.placed = { ...this.placed, [this.chosen.id]: bin };
                this.chosen = null;
                playInstrument('amadinda', this.placedCount);
                if (this.placedCount === this.items.length) {
                    setTimeout(() => this.root.dispatchEvent(new CustomEvent('sort-done', { detail: { correct: this.mistakes === 0 }, bubbles: true })), 300);
                }
            } else {
                this.mistakes++;
                this.wrongBin = bin;
                playInstrument('tak');
                setTimeout(() => (this.wrongBin = null), 500);
            }
        },
    }));
};
