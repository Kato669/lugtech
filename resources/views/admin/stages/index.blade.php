<x-admin title="Stages">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold">Stages</h1>
        <a href="{{ route('admin.stages.create') }}" class="btn-primary">New stage</a>
    </div>
    <table class="mt-6 w-full overflow-hidden rounded-2xl bg-white text-left">
        <thead class="bg-bark-50 text-sm text-bark-800"><tr><th class="px-4 py-2">#</th><th class="px-4 py-2">Stage</th><th class="px-4 py-2">Lessons</th><th></th></tr></thead>
        <tbody>
            @foreach ($stages as $stage)
                <tr class="border-t border-bark-100">
                    <td class="px-4 py-3">{{ $stage->position }}</td>
                    <td class="px-4 py-3"><span class="lug">{{ $stage->title_lg }}</span> · {{ $stage->title_en }}</td>
                    <td class="px-4 py-3">{{ $stage->lessons_count }}</td>
                    <td class="space-x-3 px-4 py-3 text-right">
                        <a href="{{ route('admin.stages.edit', $stage) }}" class="text-sm text-bark-600 hover:underline">Edit</a>
                        <x-delete-button :action="route('admin.stages.destroy', $stage)" />
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-admin>
