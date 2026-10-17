{{-- Status of each recording of a phrase or word (they are approved on the Check page). --}}
@if ($item->exists && $voiceOn && $item->currentClips->isNotEmpty())
    <div class="rounded-xl bg-bark-50 px-4 py-3 text-sm">
        <p class="font-semibold text-bark-800">Recordings</p>
        <ul class="mt-1 space-y-1">
            @foreach ($item->currentClips as $clip)
                <li class="flex flex-wrap items-center gap-2">
                    <span class="w-16 font-semibold">{{ $clip->label() }}</span>
                    <span class="badge {{ $clip->status === 'approved' ? 'bg-leaf-100 text-leaf-600' : ($clip->status === 'pending' ? 'bg-gold-300/50 text-bark-800' : 'bg-red-50 text-red-700') }}">{{ ['approved' => '✓ approved', 'pending' => 'waiting to be checked', 'flagged' => '⚑ flagged'][$clip->status] ?? $clip->status }}</span>
                    <span class="text-bark-500">{{ $clip->isGenerated() ? '🤖 generated' : '🎙️ human' }}</span>
                    @if ($clip->review_note)<span class="text-red-700">{{ $clip->review_note }}</span>@endif
                </li>
            @endforeach
        </ul>
        <a href="{{ route('check', ['show' => 'audio']) }}" class="mt-2 inline-block text-bark-600 underline">Check recordings</a>
    </div>
@endif
