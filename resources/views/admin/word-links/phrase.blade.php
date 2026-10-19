<x-admin title="Words in a phrase">
    @php($back = route('admin.word-links.index', ['phrase' => $phrase->id]))
    <a href="{{ route('admin.word-links.index') }}" class="text-sm font-semibold text-bark-600 hover:underline">← All word links</a>
    <h1 class="mt-2 text-3xl font-bold">Words in <span class="lug">{{ $phrase->luganda }}</span></h1>
    <p class="mt-1 text-bark-800">{{ $phrase->english }} · <span class="font-mono text-sm">{{ $phrase->code }}</span> ·
        <a href="{{ route('admin.phrases.edit', $phrase) }}" class="text-sm text-bark-600 hover:underline">Edit phrase</a></p>

    <div class="mt-6 space-y-4">
        @foreach ($parts as $i => $part)
            @php($entry = $part['ref'] ? ($entries[$part['ref']] ?? null) : null)
            <div class="card !p-4">
                <div class="flex flex-wrap items-baseline gap-x-3">
                    <span class="lug font-display text-2xl font-bold">{{ $part['t'] }}</span>
                    <span class="text-bark-800">
                        @if ($part['ref'] === 'name') a name
                        @elseif ($entry) → <span class="lug">{{ $entry['lg'] }}</span>: {{ $entry['en'] }}
                        @else <span class="text-bark-500">not in the dictionary yet</span>
                        @endif
                    </span>
                    <span class="badge bg-bark-50 text-bark-600">{{ ['link' => 'linked', 'word' => 'same spelling', 'phrase' => 'one-word phrase'][$part['via']] ?? 'no match' }}</span>
                </div>
                @include('admin.word-links._form', ['row' => ['form' => $part['form'], 'shown' => $part['t'], 'phrases' => collect([$phrase])], 'i' => 'p'.$i, 'suggest' => collect(), 'suggestName' => collect()])
                @if ($part['via'] === 'link')
                    <form method="POST" action="{{ route('admin.word-links.store') }}" class="mt-2">@csrf
                        <input type="hidden" name="form" value="{{ $part['form'] }}">
                        <input type="hidden" name="phrase_ids[]" value="{{ $phrase->id }}">
                        <input type="hidden" name="back" value="{{ $back }}">
                        <button name="action" value="auto" class="text-sm text-red-700 hover:underline">Remove the link (back to exact spelling only)</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</x-admin>
