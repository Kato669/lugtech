<x-admin title="Phrases">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-bold">Phrases</h1>
        <a href="{{ route('admin.phrases.create') }}" class="btn-primary">New phrase</a>
    </div>
    <form class="mt-4 flex flex-wrap gap-2" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search…" class="field max-w-xs">
        <select name="filter" class="field max-w-48">
            <option value="">All</option>
            <option value="unreviewed" @selected(request('filter') === 'unreviewed')>Needs review</option>
            <option value="no-audio" @selected(request('filter') === 'no-audio')>No audio</option>
            <option value="flagged" @selected(request('filter') === 'flagged')>Flagged by reviewer</option>
        </select>
        <button class="btn-ghost">Filter</button>
    </form>
    <div class="mt-4 overflow-hidden rounded-2xl bg-white">
        @foreach ($phrases as $phrase)
            <div class="flex flex-wrap items-center gap-3 border-b border-bark-100 px-4 py-3 last:border-0">
                <span class="font-mono text-xs text-bark-500">{{ $phrase->code }}</span>
                <span class="lug">{{ $phrase->luganda }}</span>
                <x-speak :item="$phrase" />
                <span class="flex-1 text-bark-800">{{ $phrase->english }}</span>
                <span class="text-xs text-bark-500">{{ $phrase->lessons_count }} lessons</span>
                @if ($phrase->isFlagged())
                    <span class="badge bg-red-50 text-red-700" title="{{ $phrase->review_note }}">⚑ flagged</span>
                @elseif (! $phrase->is_reviewed)
                    <span class="badge bg-gold-300/40 text-bark-800">needs review</span>
                @endif
                <a href="{{ route('admin.phrases.edit', $phrase) }}" class="text-sm text-bark-600 hover:underline">Edit</a>
                <x-delete-button :action="route('admin.phrases.destroy', $phrase)" />
            </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $phrases->links() }}</div>
</x-admin>
