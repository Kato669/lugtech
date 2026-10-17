{{--
    The classroom: the teacher (Mukasa, Nakato or Ngoma) beside a speech bubble.
    The teacher's mood and words follow the lesson (see teacherState and bubble in
    resources/js/lesson-player.js). Hidden for conversations, which show both characters.
--}}
<div class="mb-3 flex items-end gap-2 sm:gap-4" x-show="step && !finished && showTeacher" x-transition.opacity>
    <div class="relative h-32 w-20 shrink-0 sm:h-44 sm:w-28" aria-hidden="true">
        @foreach (['mukasa', 'nakato', 'ngoma'] as $who)
            <x-character :name="$who" state="teacherState" x-show="teacher === '{{ $who }}'"
                         class="absolute inset-x-0 bottom-0 {{ $who === 'ngoma' ? 'h-24 sm:h-32' : 'h-full' }} w-full" />
        @endforeach
    </div>
    <div class="teacher-bubble anim-pop mb-6 flex-1 rounded-3xl border-2 border-bark-900 bg-white px-4 py-3 shadow-[0_3px_0_#3b1f12]"
         :key="'b' + index + (checked ? (correct ? 'y' : 'n') : '')" role="status" aria-live="polite">
        <p class="text-xs font-bold uppercase tracking-wide text-bark-500" x-text="teacherLabel"></p>
        <p class="font-display text-lg font-bold leading-snug text-bark-900 sm:text-xl" x-text="bubble.text"></p>
        <p class="mt-1 flex flex-wrap items-center gap-2" x-show="bubble.luganda">
            <span class="lug text-lg" x-text="bubble.luganda"></span>
            <button type="button" class="speak-btn" x-show="bubble.audio" @click="playAudio(bubble.audio)" aria-label="Listen">🔊</button>
        </p>
    </div>
</div>
