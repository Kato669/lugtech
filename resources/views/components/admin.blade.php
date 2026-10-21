@props(['title'])
<x-layouts.app :title="$title">
    <div class="mb-6 flex flex-wrap gap-2 text-sm font-medium">
        @foreach (['admin.dashboard' => 'Overview', 'admin.stages.index' => 'Stages', 'admin.lessons.index' => 'Lessons', 'admin.phrases.index' => 'Phrases', 'admin.words.index' => 'Dictionary', 'admin.word-links.index' => 'Word links', 'admin.culture' => 'Culture', 'admin.blog.index' => 'Blog', 'admin.audio.index' => '🤖 Generate audio', 'admin.people.index' => 'People'] as $route => $label)
            <a href="{{ route($route) }}" class="rounded-full px-4 py-1.5 {{ request()->routeIs(str_replace('.index', '.*', $route)) ? 'bg-bark-600 text-white' : 'bg-white text-bark-800 hover:bg-bark-50' }}">{{ $label }}</a>
        @endforeach
    </div>
    {{ $slot }}
</x-layouts.app>
