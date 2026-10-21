/**
 * Admin → Blog editor: a Markdown textarea with a small toolbar, picture upload and a preview
 * (resources/views/admin/blog/form.blade.php, App\Http\Controllers\Admin\BlogController).
 * Luganda goes in [[double brackets]] so readers can tap each word.
 */
export default (Alpine) => {
    Alpine.data('blogEditor', (urls) => ({
        tab: 'write',
        preview: '',
        loading: false,
        uploading: false,
        error: '',
        words: 0,
        init() { this.count(); },
        get box() { return this.$refs.body; },
        count() { this.words = (this.box.value.match(/\S+/g) || []).length; },
        get minutes() { return Math.max(1, Math.ceil(this.words / 200)); },
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },

        /** Put text around the selection (or a placeholder when nothing is selected). */
        wrap(before, after = before, placeholder = 'text') {
            const b = this.box;
            const [s, e] = [b.selectionStart, b.selectionEnd];
            const chosen = b.value.slice(s, e) || placeholder;
            b.setRangeText(before + chosen + after, s, e, 'end');
            b.focus();
            b.setSelectionRange(s + before.length, s + before.length + chosen.length);
            this.count();
        },
        /** Start each selected line with a mark ("## ", "- ", "> "). */
        lines(mark) {
            const b = this.box;
            const s = b.value.lastIndexOf('\n', b.selectionStart - 1) + 1;
            const e = b.selectionEnd;
            const text = b.value.slice(s, e) || 'text';
            b.setRangeText(text.split('\n').map((l) => mark + l.replace(/^(#{1,6} |- |> |\d+\. )/, '')).join('\n'), s, Math.max(e, s), 'end');
            b.focus();
            this.count();
        },
        link() {
            const b = this.box;
            const chosen = b.value.slice(b.selectionStart, b.selectionEnd) || 'link text';
            b.setRangeText(`[${chosen}](https://)`, b.selectionStart, b.selectionEnd, 'end');
            b.focus();
            b.setSelectionRange(b.selectionEnd - 9, b.selectionEnd - 1);
        },
        insert(text) {
            const b = this.box;
            b.setRangeText(text, b.selectionStart, b.selectionEnd, 'end');
            b.focus();
            this.count();
        },

        async upload(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;
            this.uploading = true;
            this.error = '';
            try {
                const form = new FormData();
                form.append('image', file);
                const r = await fetch(urls.image, { method: 'POST', body: form, headers: { 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' } });
                const data = await r.json();
                if (!r.ok) throw new Error(data.errors?.image?.[0] || data.message || 'Upload failed.');
                this.insert(`\n\n${data.markdown}\n\n`);
            } catch (e) {
                this.error = e.message || 'Upload failed.';
            } finally {
                this.uploading = false;
            }
        },

        async showPreview() {
            this.tab = 'preview';
            this.loading = true;
            try {
                const r = await fetch(urls.preview, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ body: this.box.value }),
                });
                this.preview = r.ok ? (await r.json()).html : '<p>Preview failed. Try again.</p>';
            } catch (e) {
                this.preview = '<p>Preview needs a connection.</p>';
            } finally {
                this.loading = false;
            }
        },
    }));
};
