<x-admin title="Culture">
    <h1 class="text-3xl font-bold">Culture</h1>
    <p class="mt-1 max-w-3xl text-bark-800">Clans, names and story pages. New entries are drafts: a native speaker corrects and approves them on
        <a href="{{ route('check.culture') }}" class="underline">Check → Culture</a>. Proverbs are added under
        <a href="{{ route('admin.words.create') }}" class="underline">Dictionary → New entry</a> (type “Proverb”, with a theme and meaning).</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.culture.store', 'clan') }}" class="card space-y-3 !p-4">@csrf
            <h2 class="text-xl font-bold">Add a clan</h2>
            <x-input name="name" label="Clan (Luganda)" required />
            <x-input name="totem_lg" label="Totem (Luganda)" />
            <x-input name="totem_en" label="Totem (English)" required />
            <x-input name="notes" label="Notes" />
            <button class="btn-primary !py-2">Add clan</button>
        </form>
        <form method="POST" action="{{ route('admin.culture.store', 'name') }}" class="card space-y-3 !p-4">@csrf
            <h2 class="text-xl font-bold">Add a name</h2>
            <x-input name="name" label="Name" required />
            <div><label class="field-label" for="gender">For</label>
                <select id="gender" name="gender" class="field"><option value="male">A boy</option><option value="female">A girl</option><option value="any">Anyone</option></select></div>
            <x-input name="meaning" label="What it says (English)" required />
            <x-input name="notes" label="Notes" />
            <button class="btn-primary !py-2">Add name</button>
        </form>
        <form method="POST" action="{{ route('admin.culture.store', 'page') }}" class="card space-y-3 !p-4">@csrf
            <h2 class="text-xl font-bold">Add a story page</h2>
            <div><label class="field-label" for="story_id">Story</label>
                <select id="story_id" name="story_id" class="field">@foreach ($stories as $s)<option value="{{ $s->id }}">{{ $s->title_en }}</option>@endforeach</select></div>
            <x-input name="english" label="English" type="textarea" required />
            <x-input name="luganda" label="Luganda (optional)" type="textarea" />
            <x-input name="picture" label="Picture" help="A picture name: man, woman, child, house, sun, moon, star, cow…" />
            <button class="btn-primary !py-2">Add page</button>
        </form>
    </div>

    @foreach ([['clan', 'Clans', $clans, fn ($c) => $c->name.' · '.$c->totem_en], ['name', 'Names', $names, fn ($n) => $n->name.' · '.$n->meaning]] as [$type, $heading, $items, $label])
        <h2 class="mt-8 text-2xl font-bold">{{ $heading }}</h2>
        <div class="mt-2 overflow-hidden rounded-2xl bg-white">
            @foreach ($items as $item)
                <div class="flex flex-wrap items-center gap-3 border-b border-bark-100 px-4 py-2 last:border-0">
                    <span class="flex-1">{{ $label($item) }}</span>
                    @unless ($item->is_reviewed)<span class="badge bg-gold-300/40 text-bark-800">draft</span>@endunless
                    <x-delete-button :action="route('admin.culture.destroy', [$type, $item->id])" />
                </div>
            @endforeach
        </div>
    @endforeach

    @foreach ($stories as $story)
        <h2 class="mt-8 text-2xl font-bold">{{ $story->title_en }} <a href="{{ route('culture.story', $story->slug) }}" class="text-base font-normal text-bark-600 underline">read</a></h2>
        <div class="mt-2 overflow-hidden rounded-2xl bg-white">
            @foreach ($story->pages as $page)
                <div class="flex flex-wrap items-center gap-3 border-b border-bark-100 px-4 py-2 last:border-0">
                    <span class="w-8 font-mono text-sm">{{ $page->position }}</span>
                    <span class="min-w-0 flex-1 truncate">{{ $page->english }}</span>
                    <span class="badge {{ $page->luganda ? 'bg-bark-50 text-bark-800' : 'bg-red-50 text-red-700' }}">{{ $page->luganda ? ($page->is_reviewed ? 'Luganda ✓' : 'Luganda draft') : 'no Luganda' }}</span>
                    <x-delete-button :action="route('admin.culture.destroy', ['page', $page->id])" />
                </div>
            @endforeach
        </div>
    @endforeach
</x-admin>
