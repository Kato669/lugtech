<x-admin :title="$stage->exists ? 'Edit stage' : 'New stage'">
    <form method="POST" action="{{ $stage->exists ? route('admin.stages.update', $stage) : route('admin.stages.store') }}" class="card max-w-2xl space-y-4">
        @csrf @if ($stage->exists) @method('PUT') @endif
        <h1 class="text-2xl font-bold">{{ $stage->exists ? 'Edit stage' : 'New stage' }}</h1>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-input name="title_lg" label="Luganda title" :value="$stage->title_lg" required />
            <x-input name="title_en" label="English title" :value="$stage->title_en" required />
            <x-input name="slug" label="Slug" :value="$stage->slug" required help="Lowercase, dashes, e.g. ewaka" />
            <x-input name="position" label="Position" type="number" :value="$stage->position" required />
        </div>
        <x-input name="greeting" label="Path greeting" :value="$stage->greeting" />
        <x-input name="description" label="Description" type="textarea" :value="$stage->description" />
        <button class="btn-primary">Save</button>
    </form>
</x-admin>
