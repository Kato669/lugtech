<x-admin title="Admin">
    <h1 class="text-3xl font-bold">Content overview</h1>
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        @foreach ($counts as $label => $value)
            <div class="card !p-5">
                <p class="text-sm text-bark-500">{{ $label }}</p>
                <p class="mt-1 text-3xl font-bold">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (array_filter([$voiceOn ? ['studio', '🎙️', 'Recording studio', 'Record phrases one by one in the browser'] : null, $voiceOn ? ['studio.script', '📄', 'Recording script', 'Print or download every phrase with its code'] : null, $voiceOn ? ['studio.upload', '⬆️', 'Bulk upload', 'Drop in many recordings, matched by code'] : null, ['admin.audio.index', '🤖', 'Generate audio', 'Make draft recordings with a Luganda voice, to be checked'], ['check', '✓', 'Check the Luganda', 'Approve, correct or flag phrases and recordings'], ['admin.blog.index', '📰', 'Blog', 'Write posts, and approve comments']]) as [$route, $icon, $label, $text])
            <a href="{{ route($route) }}" class="card !p-5 transition hover:-translate-y-0.5">
                <span class="text-2xl">{{ $icon }}</span>
                <p class="mt-2 font-display text-lg font-bold">{{ $label }}</p>
                <p class="text-sm text-bark-500">{{ $text }}</p>
            </a>
        @endforeach
    </div>

    <div class="card mt-6 text-sm text-bark-800">
        <p class="font-semibold">Adding a lesson</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Add the phrases under <a class="text-bark-600 underline" href="{{ route('admin.phrases.create') }}">Phrases</a>, with recordings if you have them.</li>
            <li>Create the lesson under <a class="text-bark-600 underline" href="{{ route('admin.lessons.create') }}">Lessons</a> and give each phrase a position (1, 2, 3…).</li>
            <li>Audio: saved phrases get a Sunbird voice automatically (or use <a class="text-bark-600 underline" href="{{ route('admin.audio.index') }}">Generate audio</a>). Recording a human voice in the @if ($voiceOn)<a class="text-bark-600 underline" href="{{ route('studio') }}">studio</a>@else studio @endif is optional.</li>
            <li>When you can, have a native speaker check the spelling and listen on the <a class="text-bark-600 underline" href="{{ route('check') }}">Check</a> page.</li>
            <li>Tick “Published”. Exercises are generated automatically.</li>
        </ol>
    </div>
</x-admin>
