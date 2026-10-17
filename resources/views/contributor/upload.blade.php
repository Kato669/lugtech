<x-layouts.app title="Bulk upload">
    <div class="mx-auto max-w-3xl" x-data="bulkUpload(@js(route('studio.upload')))">
        <h1 class="text-3xl font-bold">Bulk upload recordings</h1>
        <p class="mt-1 text-bark-800">Drop in as many files as you like. Each file is matched to its phrase by the code at the start of its name (see the <a href="{{ route('studio.script') }}" class="font-semibold text-bark-600 underline">recording script</a>). Existing recordings are replaced.</p>

        <label class="mt-6 flex cursor-pointer flex-col items-center justify-center rounded-3xl border-4 border-dashed p-10 text-center transition"
               :class="over ? 'border-leaf-500 bg-leaf-100' : 'border-bark-100 bg-white hover:border-bark-500'"
               @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="over = false; add($event.dataTransfer.files)">
            <x-art.ensaasi class="h-20 w-20" />
            <span class="mt-3 font-display text-xl font-bold">Drop audio files here</span>
            <span class="text-sm text-bark-500">or tap to choose · {{ implode(', ', $extensions) }} · up to {{ $maxMb }} MB each</span>
            <input type="file" multiple accept="audio/*,.{{ implode(',.', $extensions) }}" class="sr-only" @change="add($event.target.files); $event.target.value = ''">
        </label>

        <template x-if="files.length">
            <div class="mt-6">
                <div class="flex flex-wrap items-center gap-3 text-sm font-semibold">
                    <span class="badge bg-leaf-100 px-3 py-1 text-sm text-leaf-600">✓ <span x-text="count('done')"></span> saved</span>
                    <span class="badge bg-red-50 px-3 py-1 text-sm text-red-700" x-show="count('error')">✗ <span x-text="count('error')"></span> not matched</span>
                    <span class="badge bg-bark-50 px-3 py-1 text-sm text-bark-600" x-show="count('waiting') + count('uploading')"><span x-text="count('waiting') + count('uploading')"></span> to go</span>
                    <button class="ml-auto text-bark-500 hover:text-bark-800" @click="files = files.filter(f => f.status !== 'done')" x-show="count('done')">Clear saved</button>
                </div>
                <ul class="mt-3 overflow-hidden rounded-2xl bg-white text-sm">
                    <template x-for="f in files" :key="f.id">
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-bark-100 px-4 py-2.5 last:border-0">
                            <span class="w-5 text-center" x-text="{ waiting: '·', uploading: '⏳', done: '✓', error: '✗' }[f.status]"
                                  :class="{ done: 'text-leaf-600', error: 'text-red-600' }[f.status]"></span>
                            <span class="min-w-0 flex-1 truncate font-mono text-xs" x-text="f.file.name"></span>
                            <span x-show="f.status === 'done'" class="text-leaf-600">
                                → <b x-text="f.result?.code"></b> <span x-text="f.result?.luganda"></span><span x-show="f.result?.slow"> (slow)</span><span x-show="f.result?.voice" x-text="` (${f.result?.voice}'s voice)`"></span>
                            </span>
                            <span x-show="f.status === 'error'" class="w-full pl-8 text-xs text-red-600" x-text="f.error"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bulkUpload', (url) => ({
                files: [], over: false, busy: false, n: 0,
                count(s) { return this.files.filter((f) => f.status === s).length; },
                add(list) {
                    for (const file of list) this.files.push({ id: ++this.n, file, status: 'waiting', result: null, error: null });
                    this.run();
                },
                async run() {
                    if (this.busy) return;
                    this.busy = true;
                    let f;
                    while ((f = this.files.find((x) => x.status === 'waiting'))) {
                        f.status = 'uploading';
                        const form = new FormData();
                        form.append('file', f.file);
                        try {
                            const res = await fetch(url, {
                                method: 'POST',
                                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                body: form,
                            });
                            const data = await res.json().catch(() => ({}));
                            if (!res.ok) {
                                const msg = data.errors ? Object.values(data.errors).flat()[0] : data.message;
                                throw new Error(res.status === 413 ? 'File too big for the server (raise upload_max_filesize in php.ini).' : (msg || `Upload failed (${res.status})`));
                            }
                            f.result = data;
                            f.status = 'done';
                        } catch (e) {
                            f.error = e.message;
                            f.status = 'error';
                        }
                    }
                    this.busy = false;
                },
            }));
        });
    </script>
</x-layouts.app>
