/**
 * Tap a word (Phase 11). Luganda on the page is split into word buttons (.lug-word):
 *   - on the server by <x-lug-text> (phrasebook, dictionary and phrase pages), or
 *   - in the browser by x-lug="text" (lessons, conversations, review), using the HTML the
 *     server prepared for that exact text in #lug-glossary (App\Services\Glossary).
 * Text the page didn't prepare stays plain, so answers in quizzes never become tappable.
 * Tapping opens the word sheet (<x-word-sheet>), which needs no network.
 */
let glossary;
const data = () => {
    if (glossary === undefined) {
        try {
            glossary = JSON.parse(document.getElementById('lug-glossary')?.textContent || 'null');
        } catch (e) {
            glossary = null;
        }
    }
    return glossary;
};

export default (Alpine) => {
    Alpine.directive('lug', (el, { expression }, { evaluateLater, effect }) => {
        const get = evaluateLater(expression);
        effect(() => get((text) => {
            const html = text ? data()?.html?.[text] : null;
            if (html) el.innerHTML = html;
            else el.textContent = text ?? '';
        }));
    });

    Alpine.store('wordSheet', {
        open: false,
        word: '',
        ref: '',
        entry: null,
        opener: null,
        get differs() {
            return !!this.entry && this.entry.lg.toLowerCase().replace(/[^\p{L}]/gu, '') !== this.word.toLowerCase().replace(/[^\p{L}]/gu, '');
        },
        show(button) {
            this.opener = button;
            this.word = button.textContent.trim();
            this.ref = button.dataset.ref || '';
            this.entry = (this.ref && data()?.entries?.[this.ref]) || null;
            this.open = true;
            setTimeout(() => document.querySelector('[data-sheet-close]')?.focus(), 30);
        },
        close() {
            this.open = false;
            const back = this.opener;
            this.opener = null;
            if (back && document.contains(back)) back.focus();
        },
        /** Keep the keyboard inside the sheet while it is open. */
        trap(event, sheet) {
            const items = [...sheet.querySelectorAll('button, a[href]')].filter((n) => n.offsetParent !== null);
            if (!items.length) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        },
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('.lug-word');
        if (!button) return;
        event.preventDefault();
        event.stopPropagation();
        Alpine.store('wordSheet').show(button);
    }, true);
};
