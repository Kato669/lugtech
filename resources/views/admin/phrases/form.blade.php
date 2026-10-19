<x-admin :title="$phrase->exists ? 'Edit phrase' : 'New phrase'">
    <form method="POST" enctype="multipart/form-data" action="{{ $phrase->exists ? route('admin.phrases.update', $phrase) : route('admin.phrases.store') }}" class="card max-w-2xl space-y-4">
        @csrf @if ($phrase->exists) @method('PUT') @endif
        <h1 class="text-2xl font-bold">{{ $phrase->exists ? 'Edit phrase' : 'New phrase' }}
            @if ($phrase->exists)<span class="ml-2 rounded-full bg-bark-50 px-2 py-0.5 align-middle font-mono text-sm">{{ $phrase->code }}</span>@endif</h1>
        @if ($phrase->exists)
            <p class="text-sm"><a href="{{ route('admin.word-links.index', ['phrase' => $phrase->id]) }}" class="font-semibold text-bark-600 hover:underline">Words in this phrase: what learners see when they tap them →</a></p>
        @endif
        @if ($phrase->isFlagged())
            <div class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">⚑ Flagged by {{ $phrase->reviewer?->name ?? 'a reviewer' }}: {{ $phrase->review_note }}</div>
        @elseif ($phrase->is_reviewed && $phrase->reviewer)
            <div class="rounded-xl bg-leaf-100 px-4 py-3 text-sm text-leaf-600">✓ Approved by {{ $phrase->reviewer->name }} on {{ $phrase->reviewed_at?->format('j M Y') }}</div>
        @endif
        <x-input name="luganda" label="Luganda" :value="$phrase->luganda" required />
        <x-input name="english" label="English meaning" :value="$phrase->english" required />
        <x-input name="literal" label="Literal meaning (optional)" :value="$phrase->literal" />
        <x-input name="notes" label="Notes: when to use it, respect level, tone" type="textarea" :value="$phrase->notes" />

        @foreach (['audio' => ['Recording (normal speed)', 'audio_url'], 'audio_slow' => ['Recording (slow, optional)', 'audio_slow_url']] as $field => [$label, $urlAttr])
            <div>
                <label class="field-label" for="{{ $field }}">{{ $label }}</label>
                @if ($phrase->{$urlAttr})
                    <div class="mb-2 flex items-center gap-3">
                        <audio controls src="{{ $phrase->{$urlAttr} }}" class="h-9"></audio>
                        <label class="text-sm"><input type="checkbox" name="remove_{{ $field }}" value="1"> Remove</label>
                    </div>
                @endif
                <input id="{{ $field }}" type="file" name="{{ $field }}" accept="audio/*" class="field">
                @error($field)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <p class="text-xs text-bark-500">MP3, M4A, OGG, WAV or WEBM, up to 5 MB. New recordings wait to be approved on the Check page before learners hear them.</p>
        @include('admin.partials.recordings', ['item' => $phrase])

        <label class="flex items-center gap-2"><input type="checkbox" name="is_reviewed" value="1" @checked(old('is_reviewed', $phrase->is_reviewed)) class="rounded"> Luganda text checked by a native speaker</label>
        <button class="btn-primary">Save</button>
    </form>
</x-admin>
