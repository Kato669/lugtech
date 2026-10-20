<x-admin :title="$word->exists ? 'Edit entry' : 'New entry'">
    <form method="POST" enctype="multipart/form-data" action="{{ $word->exists ? route('admin.words.update', $word) : route('admin.words.store') }}" class="card max-w-2xl space-y-4">
        @csrf @if ($word->exists) @method('PUT') @endif
        <h1 class="text-2xl font-bold">{{ $word->exists ? 'Edit entry' : 'New entry' }}</h1>
        <div>
            <label class="field-label" for="kind">Type</label>
            <select id="kind" name="kind" class="field">
                <option value="word" @selected(old('kind', $word->kind) === 'word')>Word</option>
                <option value="proverb" @selected(old('kind', $word->kind) === 'proverb')>Proverb (olugero)</option>
            </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-input name="luganda" label="Luganda" :value="$word->luganda" required />
            <x-input name="english" label="English" :value="$word->english" required />
            <x-input name="part_of_speech" label="Part of speech" :value="$word->part_of_speech" help="noun, verb, adjective…" />
            <x-input name="plural" label="Plural (nouns)" :value="$word->plural" />
            <x-input name="example_lg" label="Example (Luganda)" :value="$word->example_lg" />
            <x-input name="example_en" label="Example (English)" :value="$word->example_en" />
        </div>
        {{-- Phase 15: proverbs (shown on Culture → Proverbs) --}}
        <fieldset class="rounded-2xl bg-bark-50 p-4">
            <legend class="font-semibold">For proverbs</legend>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label class="field-label" for="theme">Theme</label>
                    <select id="theme" name="theme" class="field"><option value="">—</option>
                        @foreach (\App\Http\Controllers\CultureController::THEMES as $key => $label)<option value="{{ $key }}" @selected(old('theme', $word->theme) === $key)>{{ $label }}</option>@endforeach
                    </select></div>
                <x-input name="literal" label="Word for word" :value="$word->literal" />
                <x-input name="meaning" label="What it teaches" :value="$word->meaning" />
            </div>
        </fieldset>
        <div class="rounded-2xl bg-bark-50 p-4">
            <x-input name="syllables" label="Syllable guide" :value="$word->syllables" help="Syllables joined by hyphens, e.g. a-ma-zzi. Left empty, a draft guide is made for single words." />
            <input type="hidden" name="syllables_was_checked" value="{{ $word->syllablesChecked() ? 1 : 0 }}">
            <label class="mt-2 flex items-center gap-2"><input type="checkbox" name="syllables_checked" value="1" @checked(old('syllables_checked', $word->syllablesChecked())) class="rounded"> Syllable guide checked by a native speaker</label>
        </div>
        <div>
            <label class="field-label" for="audio">Recording</label>
            @if ($word->audio_url)<audio controls src="{{ $word->audio_url }}" class="mb-2 h-9"></audio>@endif
            <input id="audio" type="file" name="audio" accept="audio/*" class="field">
            @error('audio')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-bark-500">New recordings wait to be approved on the Check page before learners hear them.</p>
        </div>
        @include('admin.partials.recordings', ['item' => $word])
        @if ($word->exists && $word->kind === 'word')
            <div>
                <p class="field-label">Used in these course phrases</p>
                @forelse ($usedIn ?? [] as $p)
                    <a href="{{ route('admin.word-links.index', ['phrase' => $p->id]) }}" class="mr-3 text-sm text-bark-600 hover:underline"><span class="lug">{{ $p->luganda }}</span></a>
                @empty
                    <p class="text-sm text-bark-500">None yet. Link it from <a class="underline" href="{{ route('admin.word-links.index') }}">Word links</a>.</p>
                @endforelse
                <p class="mt-1 text-sm"><a href="{{ route('dictionary.word', $word->slug) }}" class="text-bark-600 hover:underline">Public page →</a></p>
            </div>
        @endif
        <label class="flex items-center gap-2"><input type="checkbox" name="is_reviewed" value="1" @checked(old('is_reviewed', $word->is_reviewed)) class="rounded"> Luganda text checked by a native speaker</label>
        <button class="btn-primary">Save</button>
    </form>
</x-admin>
