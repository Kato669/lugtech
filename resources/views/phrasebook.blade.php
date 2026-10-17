<x-layouts.app title="Phrasebook">
    <div x-data="{ q: '' }">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">Your phrasebook</h1>
                <p class="mt-1 text-bark-800">Every phrase you've learned: {{ $phrases->count() }} so far.</p>
            </div>
            <input x-model="q" placeholder="Filter…" class="field max-w-xs">
        </div>
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            @forelse ($phrases as $phrase)
                <div class="card !p-4" x-show="!q || @js(mb_strtolower($phrase->luganda.' '.$phrase->english)).includes(q.toLowerCase())">
                    <p class="text-lg lug">{{ $phrase->luganda }} <x-speak :item="$phrase" /></p>
                    <p class="text-bark-800">{{ $phrase->english }}</p>
                </div>
            @empty
                <p class="text-bark-500">Finish a lesson and its phrases will appear here.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
