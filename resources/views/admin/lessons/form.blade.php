<x-admin :title="$lesson->exists ? 'Edit lesson' : 'New lesson'">
    <form method="POST" action="{{ $lesson->exists ? route('admin.lessons.update', $lesson) : route('admin.lessons.store') }}" class="space-y-6" x-data="{ q: '' }">
        @csrf @if ($lesson->exists) @method('PUT') @endif
        <div class="card space-y-4">
            <h1 class="text-2xl font-bold">{{ $lesson->exists ? 'Edit lesson' : 'New lesson' }}</h1>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="title" label="Title" :value="$lesson->title" required />
                <x-input name="slug" label="Slug" :value="$lesson->slug" required help="Used in the URL, e.g. greetings-1" />
                <div>
                    <label class="field-label" for="stage_id">Stage</label>
                    <select id="stage_id" name="stage_id" class="field" required>
                        @foreach ($stages as $stage)
                            <option value="{{ $stage->id }}" @selected(old('stage_id', $lesson->stage_id) == $stage->id)>{{ $stage->position }}. {{ $stage->title_lg }} · {{ $stage->title_en }}</option>
                        @endforeach
                    </select>
                </div>
                <x-input name="position" label="Position in stage" type="number" :value="$lesson->position" required />
            </div>
            <x-input name="goal" label="Goal (what learners can do after)" type="textarea" :value="$lesson->goal" />
            <label class="flex items-center gap-2"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $lesson->is_published)) class="rounded"> Published (visible to learners)</label>
        </div>

        <div class="card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold">Phrases</h2>
                    <p class="text-sm text-bark-500">Give a position (1, 2, 3…) to each phrase in this lesson. Leave blank to leave it out. 4–8 phrases works well.</p>
                </div>
                <input x-model="q" placeholder="Filter phrases…" class="field max-w-xs">
            </div>
            <div class="mt-4 max-h-[28rem] overflow-y-auto rounded-xl border border-bark-100">
                @foreach ($phrases as $phrase)
                    @php $pos = old("phrases.{$phrase->id}", $attached->get($phrase->id)); @endphp
                    <label class="flex items-center gap-3 border-b border-bark-100 px-3 py-2 last:border-0 {{ $pos ? 'bg-leaf-100/50' : '' }}"
                           x-show="!q || @js(mb_strtolower($phrase->luganda.' '.$phrase->english)).includes(q.toLowerCase())">
                        <input type="number" min="1" name="phrases[{{ $phrase->id }}]" value="{{ $pos }}" class="field !w-20 !py-1">
                        <span class="lug">{{ $phrase->luganda }}</span>
                        <span class="text-sm text-bark-800">{{ $phrase->english }}</span>
                        @unless ($phrase->audio_path)<span class="badge bg-bark-50 text-bark-500">no audio</span>@endunless
                        @unless ($phrase->is_reviewed)<span class="badge bg-gold-300/40 text-bark-800">needs review</span>@endunless
                    </label>
                @endforeach
            </div>
            <a href="{{ route('admin.phrases.create') }}" class="mt-3 inline-block text-sm text-bark-600 hover:underline" target="_blank">+ Add a new phrase (opens in new tab, reload after)</a>
        </div>
        <button class="btn-primary">Save lesson</button>
    </form>
</x-admin>
