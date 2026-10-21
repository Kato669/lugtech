<x-admin title="Instrument sounds">
    <h1 class="text-3xl font-bold">Instrument sounds</h1>
    <p class="mt-1 max-w-3xl text-bark-800">The five instruments in “Sounds you'll hear” on the <a href="{{ route('home') }}#sounds" class="underline">home page</a>.
        Upload a real recording of each one: tapping the instrument plays it on a loop until it is tapped again, or another one is tapped.
        Without a recording, a built-in rhythm plays instead.</p>
    <ul class="mt-3 max-w-3xl list-disc space-y-1 pl-5 text-sm text-bark-800">
        <li><strong>Best:</strong> 10 to 30 seconds of the instrument on its own, playing a steady rhythm, with no talking or clapping. Cut it so the end runs smoothly into the start.</li>
        <li>MP3, M4A, OGG, WAV, WebM or AAC, up to 10 MB.</li>
        <li><strong>Only upload recordings you made, or that you have permission to use</strong> (for example, a musician who agreed, or a licence that allows it).</li>
    </ul>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach (\App\Support\InstrumentSounds::INSTRUMENTS as $key => [$name, $what])
            <div class="card space-y-3 !p-4">
                <div class="flex items-center gap-3">
                    <x-dynamic-component :component="'art.'.$key" class="h-14 w-14" />
                    <div class="flex-1">
                        <h2 class="text-xl font-bold">{{ $name }}</h2>
                        <p class="text-sm text-bark-500">{{ $what }}</p>
                    </div>
                    <span @class(['badge', 'bg-leaf-100 text-leaf-600' => $urls[$key], 'bg-bark-50 text-bark-800' => ! $urls[$key]])>{{ $urls[$key] ? '🎙️ Real recording' : 'Built-in rhythm' }}</span>
                </div>
                @if ($urls[$key])
                    <audio controls preload="none" src="{{ $urls[$key] }}" class="w-full" aria-label="{{ $name }} recording"></audio>
                @endif
                <form method="POST" action="{{ route('admin.instruments.store', $key) }}" enctype="multipart/form-data" class="space-y-2">@csrf
                    <label for="recording-{{ $key }}" class="field-label">{{ $urls[$key] ? 'Replace the recording' : 'Upload a recording' }}</label>
                    <input id="recording-{{ $key }}" name="recording" type="file" accept="audio/*" required class="block w-full text-sm">
                    @if ($errors->has('recording') && old('_key') === $key)<p class="text-sm text-red-600">{{ $errors->first('recording') }}</p>@endif
                    <input type="hidden" name="_key" value="{{ $key }}">
                    <button class="btn-primary !py-1.5 text-sm">Save</button>
                </form>
                @if ($urls[$key])
                    <form method="POST" action="{{ route('admin.instruments.destroy', $key) }}" onsubmit="return confirm('Remove this recording? The built-in rhythm will play again.')">@csrf @method('DELETE')
                        <button class="text-sm text-red-600 hover:underline">Remove the recording</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</x-admin>
