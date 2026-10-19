<x-admin title="Word links">
    @php($back = route('admin.word-links.index'))
    <h1 class="text-3xl font-bold">Word links</h1>
    <p class="mt-1 max-w-3xl text-bark-800">Learners can tap any Luganda word to see its meaning. A word is found when it's spelled exactly like a dictionary word
        (or a one-word phrase). Luganda joins words together, so many aren't: <span class="lug">ntebe</span> in <span class="lug">Tuula ku ntebe</span> is
        <span class="lug">entebe</span>. Link those here. Anything not linked shows “not in the dictionary yet”, never a guess.</p>
    <p class="mt-2 text-sm text-bark-500">{{ $total }} different words in the course · {{ $missing->count() }} with no meaning yet · {{ $phraseOnly->count() }} known only from a phrase · {{ $links->count() }} links</p>

    <section class="mt-8">
        <h2 class="text-2xl font-bold">No meaning yet ({{ $missing->count() }})</h2>
        @forelse ($missing as $i => $row)
            <details class="card mt-3 !p-4">
                <summary class="flex cursor-pointer flex-wrap items-baseline gap-x-3">
                    <span class="lug font-display text-xl font-bold">{{ $row['shown'] }}</span>
                    <span class="text-sm text-bark-500">in {{ $row['phrases']->count() }} {{ str('phrase')->plural($row['phrases']->count()) }}</span>
                    @if (isset($suggest[$row['form']]))<span class="badge bg-leaf-100 text-leaf-600">suggestion</span>@endif
                </summary>
                @include('admin.word-links._form', ['row' => $row, 'i' => 'm'.$i])
            </details>
        @empty
            <p class="mt-2 text-bark-800">Every word in the course has a meaning. 🎉</p>
        @endforelse
    </section>

    <section class="mt-10">
        <h2 class="text-2xl font-bold">Known only from a one-word phrase ({{ $phraseOnly->count() }})</h2>
        <p class="mt-1 text-sm text-bark-800">Tapping these shows the phrase's meaning. Add them to the dictionary to give them a dictionary page and a syllable guide.</p>
        <div class="mt-3 overflow-hidden rounded-2xl bg-white">
            @foreach ($phraseOnly as $row)
                @php($source = \App\Models\Phrase::find((int) substr($row['ref'], 1)))
                <div class="flex flex-wrap items-center gap-3 border-b border-bark-100 px-4 py-2 last:border-0">
                    <span class="lug font-semibold">{{ $row['shown'] }}</span>
                    <span class="flex-1 text-bark-800">{{ $source?->english }}</span>
                    @if ($source)
                        <form method="POST" action="{{ route('admin.word-links.from-phrase', $source) }}">@csrf
                            <input type="hidden" name="back" value="{{ $back }}">
                            <button class="text-sm font-semibold text-bark-600 hover:underline">+ Add to dictionary (draft)</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-10">
        <h2 class="text-2xl font-bold">Links ({{ $links->count() }})</h2>
        <div class="mt-3 overflow-hidden rounded-2xl bg-white">
            @forelse ($links as $link)
                <div class="flex flex-wrap items-center gap-3 border-b border-bark-100 px-4 py-2 last:border-0">
                    <span class="lug font-semibold">{{ $link->form }}</span>
                    <span>→ @if ($link->is_name)<span class="text-bark-500">a name</span>@else<span class="lug">{{ $link->word?->luganda }}</span> <span class="text-bark-500">({{ $link->word?->english }})</span>@endif</span>
                    <a href="{{ route('admin.word-links.index', ['phrase' => $link->phrase_id]) }}" class="flex-1 text-sm text-bark-800 hover:underline">in <span class="lug">{{ $link->phrase?->luganda }}</span></a>
                    <form method="POST" action="{{ route('admin.word-links.destroy', $link) }}">@csrf @method('DELETE')
                        <input type="hidden" name="back" value="{{ $back }}">
                        <button class="text-sm text-red-700 hover:underline">Remove</button>
                    </form>
                </div>
            @empty
                <p class="px-4 py-3 text-bark-500">No links yet.</p>
            @endforelse
        </div>
    </section>
</x-admin>
