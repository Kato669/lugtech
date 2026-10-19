{{-- One course word: link it to a dictionary word, mark it as a name, or add it as a draft word. --}}
@php
    $id = 'wl-'.$i;
    $suggested = $suggest[$row['form']] ?? null;
@endphp
<form method="POST" action="{{ route('admin.word-links.store') }}" class="mt-3 space-y-3 rounded-2xl bg-bark-50 p-3">
    @csrf
    <input type="hidden" name="form" value="{{ $row['form'] }}">
    <input type="hidden" name="back" value="{{ $back }}">
    <fieldset>
        <legend class="text-xs font-bold uppercase tracking-wide text-bark-500">In these phrases</legend>
        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
            @foreach ($row['phrases'] as $p)
                <label class="text-sm"><input type="checkbox" name="phrase_ids[]" value="{{ $p->id }}" checked class="rounded">
                    <span class="lug">{{ $p->luganda }}</span> <span class="text-bark-500">({{ $p->english }})</span></label>
            @endforeach
        </div>
    </fieldset>

    <div class="flex flex-wrap items-end gap-2">
        <div class="min-w-0 flex-1">
            <label class="field-label" for="{{ $id }}-word">Same word as…</label>
            <select id="{{ $id }}-word" name="word_id" class="field">
                <option value="">Choose a dictionary word</option>
                @foreach ($words as $w)
                    <option value="{{ $w->id }}" @selected($suggested?->id === $w->id)>{{ $w->luganda }} · {{ $w->english }}</option>
                @endforeach
            </select>
        </div>
        <button name="action" value="link" class="btn-primary !py-2">Link</button>
        <button name="action" value="name" class="btn-ghost !py-2" @if (isset($suggestName[$row['form']])) title="Marked as a name elsewhere" @endif>It's a name</button>
    </div>
    @if ($suggested)
        <p class="text-xs text-leaf-600">Suggested: “{{ $row['form'] }}” is linked to <span class="lug">{{ $suggested->luganda }}</span> in another phrase.</p>
    @endif

    <details>
        <summary class="cursor-pointer text-sm font-semibold text-bark-600">Not in the dictionary? Add it as a draft</summary>
        <div class="mt-2 grid gap-2 sm:grid-cols-3">
            <div><label class="field-label" for="{{ $id }}-lg">Dictionary form (Luganda)</label>
                <input id="{{ $id }}-lg" name="luganda" value="{{ $row['shown'] }}" class="field"></div>
            <div><label class="field-label" for="{{ $id }}-en">Meaning (English)</label>
                <input id="{{ $id }}-en" name="english" class="field"></div>
            <div><label class="field-label" for="{{ $id }}-pos">Part of speech</label>
                <input id="{{ $id }}-pos" name="part_of_speech" class="field" placeholder="noun, verb…"></div>
        </div>
        <p class="mt-1 text-xs text-bark-500">It goes to the Check page as a draft. Type the meaning yourself: nothing is filled in by guesswork.</p>
        <button name="action" value="new" class="btn-leaf mt-2 !py-2">Add and link</button>
    </details>
</form>
