<x-admin title="Lessons">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold">Lessons</h1>
        <a href="{{ route('admin.lessons.create') }}" class="btn-primary">New lesson</a>
    </div>
    @foreach ($stages as $stage)
        <section class="mt-8">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold">{{ $stage->position }}. {{ $stage->title_lg }} <span class="font-normal text-bark-500">· {{ $stage->title_en }}</span></h2>
                <a href="{{ route('admin.lessons.create', ['stage' => $stage->id]) }}" class="text-sm text-bark-600 hover:underline">+ Add lesson</a>
            </div>
            <div class="mt-3 overflow-hidden rounded-2xl bg-white">
                @forelse ($stage->lessons as $lesson)
                    <div class="flex flex-wrap items-center gap-3 border-b border-bark-100 px-4 py-3 last:border-0">
                        <span class="w-6 text-bark-500">{{ $lesson->position }}</span>
                        <span class="flex-1 font-semibold">{{ $lesson->title }}</span>
                        <span class="text-sm text-bark-500">{{ $lesson->phrases_count }} phrases</span>
                        <span class="badge {{ $lesson->isScripted() ? 'bg-gold-300/50 text-bark-800' : 'bg-bark-50 text-bark-500' }}" title="{{ $lesson->isScripted() ? 'Plays its own steps (php artisan app:lesson-check '.$lesson->slug.')' : 'Exercises generated from its phrases' }}">{{ $lesson->isScripted() ? '🎬 scripted' : 'auto' }}</span>
                        <span class="badge {{ $lesson->is_published ? 'bg-leaf-100 text-leaf-600' : 'bg-bark-50 text-bark-500' }}">{{ $lesson->is_published ? 'Published' : 'Draft' }}</span>
                        <a href="{{ route('lessons.show', $lesson) }}" class="text-sm text-bark-600 hover:underline">Preview</a>
                        <a href="{{ route('admin.lessons.edit', $lesson) }}" class="text-sm text-bark-600 hover:underline">Edit</a>
                        <x-delete-button :action="route('admin.lessons.destroy', $lesson)" />
                    </div>
                @empty
                    <p class="px-4 py-3 text-sm text-bark-500">No lessons yet.</p>
                @endforelse
            </div>
        </section>
    @endforeach
</x-admin>
