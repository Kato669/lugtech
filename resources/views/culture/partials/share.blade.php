{{-- Share a proverb: the phone's share sheet, or copy the link (no pop-up dialogs). --}}
<button type="button" x-data="{ done: false }" class="{{ $class ?? 'btn-ghost !py-2 text-sm' }}"
        @click="const data = { title: 'Luganda proverb', text: @js($text), url: @js($url) };
                if (navigator.share) { navigator.share(data).catch(() => {}); }
                else { navigator.clipboard?.writeText(data.text + ' ' + data.url).then(() => { done = true; setTimeout(() => done = false, 2000); }); }">
    <span x-text="done ? '✓ Link copied' : '↗ Share'">↗ Share</span>
</button>
