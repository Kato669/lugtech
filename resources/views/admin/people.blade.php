<x-admin title="People">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold">People</h1>
            <p class="text-bark-800">Make a native speaker a <b>reviewer</b> so they can use the recording studio and check the Luganda. They need to create an account first.</p>
        </div>
        <form method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search name or email…" class="field max-w-xs"></form>
    </div>
    <div class="mt-6 overflow-hidden rounded-2xl bg-white">
        @foreach ($people as $person)
            <div class="flex flex-wrap items-center gap-3 border-b border-bark-100 px-4 py-3 last:border-0">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $person->name }}
                        @if ($person->is_admin)<span class="badge bg-bark-600 text-white">admin</span>@endif
                        @if ($person->is_reviewer)<span class="badge bg-leaf-100 text-leaf-600">reviewer</span>@endif
                    </p>
                    <p class="truncate text-sm text-bark-500">{{ $person->email }} · joined {{ $person->created_at?->format('j M Y') }}</p>
                </div>
                <span class="text-xs text-bark-500">🎙️ {{ $recorded[$person->id] ?? 0 }} recorded · ✓ {{ $reviewed[$person->id] ?? 0 }} checked</span>
                <form method="POST" action="{{ route('admin.people.update', $person) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="is_reviewer" value="{{ $person->is_reviewer ? 0 : 1 }}">
                    <button class="{{ $person->is_reviewer ? 'btn-ghost' : 'btn-leaf' }} !px-3 !py-1.5 text-sm">{{ $person->is_reviewer ? 'Remove reviewer' : 'Make reviewer' }}</button>
                </form>
            </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $people->links() }}</div>
</x-admin>
