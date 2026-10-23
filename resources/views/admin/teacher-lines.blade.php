<x-admin title="Mukasa's lines">
    <h1 class="text-3xl font-bold">🎙️ Mukasa's lines</h1>
    <p class="mt-1 max-w-3xl text-bark-800">Everything Mukasa says in English in the lessons. Record a line and learners hear it in your voice;
        a line that isn't recorded yet is read by the phone's built-in voice. Learners can switch this off in Settings → Sound.</p>
    <ul class="mt-3 max-w-3xl list-disc space-y-1 pl-5 text-sm text-bark-800">
        <li>A man's voice, warm and clear, like a friendly teacher. Read it just as it's written, in a quiet room.</li>
        <li>Luganda inside a line (like “Kulika!”) is said the Luganda way.</li>
        <li>Tap <strong>🎙️ Record</strong>, speak, tap <strong>■ Stop</strong>, listen, then <strong>Save</strong>. Or upload a file (MP3, M4A, OGG, WAV or WebM).</li>
    </ul>

    <div class="mt-5 flex flex-wrap items-center gap-2 text-sm font-semibold">
        @foreach (['todo' => 'Not recorded', 'done' => 'Recorded', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.teacher-lines', ['show' => $key, 'q' => $q ?: null]) }}" @class(['rounded-full px-4 py-1.5', 'bg-bark-600 text-white' => $show === $key, 'bg-white hover:bg-bark-50' => $show !== $key]) @if ($show === $key) aria-current="page" @endif>{{ $label }} ({{ $counts[$key] }})</a>
        @endforeach
        <form method="GET" class="ml-auto flex gap-2">
            <input type="hidden" name="show" value="{{ $show }}">
            <label for="q" class="sr-only">Search the lines</label>
            <input id="q" name="q" value="{{ $q }}" placeholder="Search the lines" class="field !w-48 !py-1.5">
            <button class="btn-ghost !py-1.5 text-sm">Search</button>
        </form>
    </div>
    <p class="mt-3 text-sm text-bark-500">{{ $counts['done'] }} of {{ $counts['all'] }} lines recorded. Lines from Mukasa's own lessons come first.</p>

    <div class="mt-4 space-y-3">
        @forelse ($lines as $line)
            <div class="card !p-4" x-data="lineRecorder(@js(['text' => $line['text'], 'url' => $line['recording']?->url(), 'save' => route('admin.teacher-lines.store')]))">
                <p class="font-display text-lg font-bold">“{{ $line['text'] }}”</p>
                <p class="mt-0.5 text-xs text-bark-500">{{ implode(' · ', array_slice($line['where'], 0, 4)) }}@if (count($line['where']) > 4) · and {{ count($line['where']) - 4 }} more @endif
                    @unless ($line['his']) · <span class="italic">when a learner chooses Mukasa as their teacher</span>@endunless</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="badge" :class="saved ? 'bg-leaf-100 text-leaf-600' : 'bg-bark-50 text-bark-800'" x-text="saved ? '🎙️ Recorded' : 'Built-in voice'"></span>
                    <button type="button" class="btn-ghost !py-1.5 text-sm" x-show="saved" @click="playAudio(saved)">▶ Listen</button>
                    <button type="button" class="btn-ghost !py-1.5 text-sm" x-show="!saved" @click="window.lugTeacher?.preview(@js($line['text']))">▶ Built-in voice</button>
                    <button type="button" class="btn-primary !py-1.5 text-sm" @click="toggle()" :class="recording && 'ring-4 ring-red-300'" x-text="recording ? '■ Stop' : (take ? '🎙️ Record again' : '🎙️ Record')"></button>
                    <template x-if="take && !recording">
                        <span class="flex gap-2">
                            <button type="button" class="btn-ghost !py-1.5 text-sm" @click="playAudio(take)">▶ My take</button>
                            <button type="button" class="btn-leaf !py-1.5 text-sm" @click="upload()" :disabled="busy" x-text="busy ? 'Saving…' : 'Save'"></button>
                        </span>
                    </template>
                    <span class="text-sm font-semibold" :class="error ? 'text-red-600' : 'text-leaf-600'" x-text="error || message" role="status"></span>
                </div>
                <details class="mt-2 text-sm">
                    <summary class="cursor-pointer text-bark-600">Upload a file instead</summary>
                    <form method="POST" action="{{ route('admin.teacher-lines.store') }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">@csrf
                        <input type="hidden" name="text" value="{{ $line['text'] }}">
                        <label class="sr-only" for="file-{{ $line['key'] }}">Recording of “{{ $line['text'] }}”</label>
                        <input id="file-{{ $line['key'] }}" type="file" name="recording" accept="audio/*" required class="text-sm">
                        <button class="btn-ghost !py-1.5 text-sm">Upload</button>
                    </form>
                </details>
                @if ($line['recording'])
                    <form method="POST" action="{{ route('admin.teacher-lines.destroy', $line['recording']->id) }}" class="mt-2" onsubmit="return confirm('Remove this recording? The built-in voice reads the line again.')">@csrf @method('DELETE')
                        <button class="text-sm text-red-600 hover:underline">Remove the recording</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="card text-center text-bark-800">{{ $show === 'todo' ? 'Every line is recorded. Webale nnyo!' : 'No lines here.' }}</p>
        @endforelse
    </div>
    @if ($errors->any())<p class="mt-3 text-sm text-red-600">{{ $errors->first() }}</p>@endif

    @if ($pages > 1)
        <nav class="mt-6 flex items-center justify-center gap-3 text-sm font-semibold" aria-label="Pages">
            @if ($page > 1)<a href="{{ route('admin.teacher-lines', ['show' => $show, 'q' => $q ?: null, 'page' => $page - 1]) }}" class="btn-ghost !py-2">← Previous</a>@endif
            <span class="text-bark-500">Page {{ $page }} of {{ $pages }}</span>
            @if ($page < $pages)<a href="{{ route('admin.teacher-lines', ['show' => $show, 'q' => $q ?: null, 'page' => $page + 1]) }}" class="btn-ghost !py-2">Next →</a>@endif
        </nav>
    @endif
</x-admin>
