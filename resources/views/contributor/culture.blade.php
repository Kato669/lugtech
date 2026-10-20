{{-- Check → Culture (Phase 15): story pages, clans and names. Every bit of Luganda here started as a draft. --}}
<x-layouts.app title="Check the culture pages">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('check') }}" class="text-sm font-semibold text-bark-600 hover:underline">← Check the Luganda</a>
        <h1 class="mt-2 text-3xl font-bold">Culture to check</h1>
        <p class="mt-1 text-bark-800">The story <em>Kintu ne Nambi</em>, the clans and the names were written as drafts. Correct anything, then approve it.
            Story pages without Luganda need it written: the English is there to guide you. Proverbs are checked with the other words on the
            <a href="{{ route('check', ['show' => 'all', 'q' => '']) }}" class="underline">Check page</a> (group “Proverbs”).</p>
        <div class="mt-4 flex flex-wrap gap-2 text-sm font-semibold">
            <a href="?" class="rounded-full px-4 py-1.5 {{ $all ? 'bg-gold-300/60 hover:brightness-95' : 'bg-bark-600 text-white' }}">To check <span class="opacity-90">{{ $pending }}</span></a>
            <a href="?show=all" class="rounded-full px-4 py-1.5 {{ $all ? 'bg-bark-600 text-white' : 'bg-white hover:bg-bark-50' }}">Everything</a>
        </div>

        @foreach ([['page', 'Story pages', $pages], ['clan', 'Clans', $clans], ['name', 'Names', $names]] as [$type, $heading, $items])
            <section class="mt-8" aria-labelledby="h-{{ $type }}">
                <h2 id="h-{{ $type }}" class="text-2xl font-bold">{{ $heading }} <span class="text-base font-normal text-bark-500">({{ $items->count() }})</span></h2>
                @forelse ($items as $item)
                    @php($id = $type.'-'.$item->id)
                    <form id="{{ $id }}" method="POST" action="{{ route('check.culture.update', [$type, $item->id]) }}" class="card mt-3 space-y-3 !p-4">
                        @csrf
                        <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-wide text-bark-500">
                            @if ($type === 'page')<span>{{ $item->story->title_lg }} · page {{ $item->position }}</span>@endif
                            @if ($item->is_reviewed)<span class="badge bg-leaf-100 normal-case text-leaf-600">✓ Approved</span>@elseif ($item->isFlagged())<span class="badge bg-red-50 normal-case text-red-700">⚑ {{ $item->review_note }}</span>@else<span class="badge bg-gold-300/50 normal-case text-bark-800">Draft</span>@endif
                        </div>
                        @if ($type === 'page')
                            <div><label class="field-label" for="{{ $id }}-lg">Luganda</label>
                                <textarea id="{{ $id }}-lg" name="luganda" rows="3" class="field font-display text-lg" placeholder="Not written yet">{{ old('luganda', $item->luganda) }}</textarea></div>
                            <div><label class="field-label" for="{{ $id }}-en">English</label>
                                <textarea id="{{ $id }}-en" name="english" rows="3" class="field">{{ old('english', $item->english) }}</textarea></div>
                        @elseif ($type === 'clan')
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div><label class="field-label" for="{{ $id }}-n">Clan</label><input id="{{ $id }}-n" name="name" value="{{ $item->name }}" class="field font-display text-lg"></div>
                                <div><label class="field-label" for="{{ $id }}-tl">Totem (Luganda)</label><input id="{{ $id }}-tl" name="totem_lg" value="{{ $item->totem_lg }}" class="field"></div>
                                <div><label class="field-label" for="{{ $id }}-te">Totem (English)</label><input id="{{ $id }}-te" name="totem_en" value="{{ $item->totem_en }}" class="field"></div>
                            </div>
                            <div><label class="field-label" for="{{ $id }}-no">Notes</label><input id="{{ $id }}-no" name="notes" value="{{ $item->notes }}" class="field"></div>
                        @else
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div><label class="field-label" for="{{ $id }}-n">Name</label><input id="{{ $id }}-n" name="name" value="{{ $item->name }}" class="field font-display text-lg"></div>
                                <div><label class="field-label" for="{{ $id }}-m">What it says</label><input id="{{ $id }}-m" name="meaning" value="{{ $item->meaning }}" class="field"></div>
                            </div>
                            <div><label class="field-label" for="{{ $id }}-no">Notes</label><input id="{{ $id }}-no" name="notes" value="{{ $item->notes }}" class="field"></div>
                        @endif
                        <div class="flex flex-wrap items-end gap-2">
                            <button name="action" value="approve" class="btn-leaf !py-2">✓ Approve</button>
                            <button name="action" value="save" class="btn-ghost !py-2">Save</button>
                            <div class="flex min-w-0 flex-1 items-end gap-2">
                                <div class="min-w-0 flex-1"><label class="field-label" for="{{ $id }}-f">Flag with a note</label><input id="{{ $id }}-f" name="note" class="field" placeholder="What's wrong?"></div>
                                <button name="action" value="flag" class="btn-ghost !py-2 text-red-700">⚑ Flag</button>
                            </div>
                        </div>
                    </form>
                @empty
                    <p class="mt-2 text-bark-800">Nothing to check here. Webale nnyo!</p>
                @endforelse
            </section>
        @endforeach
    </div>
</x-layouts.app>
