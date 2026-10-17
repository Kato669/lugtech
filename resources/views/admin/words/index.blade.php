<x-admin title="Dictionary">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-bold">Dictionary &amp; proverbs</h1>
        <a href="{{ route('admin.words.create') }}" class="btn-primary">New entry</a>
    </div>
    <form class="mt-4 flex gap-2" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search…" class="field max-w-xs">
        <button class="btn-ghost">Search</button>
    </form>
    <div class="mt-4 overflow-hidden rounded-2xl bg-white">
        @foreach ($words as $word)
            <div class="flex flex-wrap items-center gap-3 border-b border-bark-100 px-4 py-3 last:border-0">
                <span class="badge {{ $word->kind === 'proverb' ? 'bg-gold-300/40 text-bark-800' : 'bg-bark-50 text-bark-600' }}">{{ $word->kind }}</span>
                <span class="lug">{{ $word->luganda }}</span>
                <span class="flex-1 text-bark-800">{{ $word->english }}</span>
                @unless ($word->is_reviewed)<span class="badge bg-gold-300/40 text-bark-800">needs review</span>@endunless
                <a href="{{ route('admin.words.edit', $word) }}" class="text-sm text-bark-600 hover:underline">Edit</a>
                <x-delete-button :action="route('admin.words.destroy', $word)" />
            </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $words->links() }}</div>
</x-admin>
