<x-layouts.app :title="$lesson->title">
    <div class="mx-auto max-w-2xl" x-data="lessonPlayer(@js($steps), @js(route('lessons.complete', $lesson)), @js($teacher))" data-lesson="{{ $lesson->id }}" x-cloak
         @converse-done="converseDone($event.detail)" @match-done="matchDone($event.detail)" @sort-done="sortDone($event.detail)">
        <h1 class="sr-only">{{ $lesson->title }}</h1>
        <div class="mb-6 flex items-center gap-4">
            <a href="{{ route('course') }}" class="text-2xl text-bark-500 hover:text-bark-800" title="Leave lesson">×</a>
            <div class="relative h-4 flex-1 rounded-full bg-bark-100">
                <div class="h-full rounded-full bg-leaf-500 shadow-[inset_0_-4px_0_#2f652c] transition-all duration-500" :style="`width: ${progress}%`"></div>
                <div class="absolute -top-2 transition-all duration-500" :style="`left: calc(${progress}% - 14px)`"><x-art.cowrie class="h-6 w-8 drop-shadow" /></div>
            </div>
            <span class="badge bg-gold-300/60 px-3 py-1 text-sm text-bark-800" x-show="streak >= 2 && !finished" x-transition>🔥 <span x-text="streak"></span> in a row</span>
        </div>

        @if (empty($steps))
            <div class="card text-center">This lesson has no phrases yet.</div>
        @endif

        {{-- The teacher, talking and reacting --}}
        @include('lesson.partials.classroom')

        {{-- Active step (the "board") --}}
        <template x-if="step && !finished">
            <div class="card anim-pop min-h-80" :key="index">
                {{-- One view per kind of step: resources/views/lesson/steps/*.blade.php --}}
                @foreach (['intro', 'learn', 'speak', 'choice', 'find', 'build', 'match', 'pattern', 'sort', 'dialogue', 'converse', 'reward'] as $view)
                    @include('lesson.steps.'.$view)
                @endforeach
            </div>
        </template>

        {{-- Feedback + action bar --}}
        <div x-show="step && !finished" class="mt-4 flex items-center justify-between gap-4 rounded-3xl p-4 transition"
             :class="checked ? (correct ? 'bg-leaf-100' : 'bg-red-50') : ''">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-2xl font-bold text-white" x-show="checked"
                     :class="correct ? 'anim-pop bg-leaf-500' : 'anim-wiggle bg-red-500'" x-text="correct ? '✓' : '✗'" aria-hidden="true"></div>
                <div>
                <template x-if="checked && correct">
                    <p class="font-display text-xl font-bold text-leaf-600">Correct!</p>
                </template>
                <template x-if="checked && !correct">
                    <div>
                        <p class="font-bold text-red-700" x-text="wrongText"></p>
                        <p class="text-red-800" x-text="step.answer"></p>
                    </div>
                </template>
                </div>
            </div>
            <button type="button" class="btn-primary min-w-32" :disabled="!canCheck" @click="advance()" x-text="buttonLabel"></button>
        </div>

        {{-- Finished --}}
        <template x-if="finished">
            <div class="card relative overflow-hidden text-center">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-48" aria-hidden="true">
                    <template x-for="n in 12" :key="n">
                        <x-art.cowrie class="anim-rise absolute bottom-0 h-6 w-8 opacity-90" ::style="`left:${n * 7 + 6}%; animation-delay:${(n % 6) * 0.15}s`" />
                    </template>
                </div>
                <x-art.ngoma mood="wow" class="anim-pop relative mx-auto h-32 w-32" />
                <h2 class="mt-3 text-4xl font-bold">Kulika! Lesson complete.</h2>
                <p class="mt-2 text-bark-800">You scored <strong x-text="score + '%'"></strong>.</p>
                <template x-if="result">
                    <p class="mt-4 text-lg">🐚 +<span x-text="result.cowries_earned"></span> cowries · 🔥 <span x-text="result.streak"></span>-day streak</p>
                </template>
                <p x-show="savedOffline" class="mx-auto mt-4 max-w-sm rounded-2xl bg-gold-300/40 px-4 py-3 text-sm font-semibold text-bark-800" role="status">📱 Saved on this phone. Your cowries and streak are added when you're back online.</p>
                <p x-show="saveError" class="mt-4 text-sm text-red-600">Couldn't save your progress. Check your connection and try again.</p>
                @if (auth()->user()->is_guest)
                    <div class="mx-auto mt-6 max-w-sm rounded-2xl bg-leaf-100 p-4">
                        <p class="text-sm text-bark-800">Learning as a guest. Create a free account to keep your progress on every device.</p>
                        <a href="{{ route('register') }}" class="btn-leaf mt-3 w-full">Save my progress</a>
                    </div>
                @endif
                <a href="{{ route('course') }}" class="btn-primary mt-6">Back to your path</a>
            </div>
        </template>
    </div>

    {{-- Mukasa's recorded lines (Admin → Mukasa's lines); the rest are read by the built-in voice (resources/js/teacher-voice.js) --}}
    <script>window.LUGANDA_TEACHER_VOICE = @js(\App\Support\TeacherLines::recordings('mukasa'));</script>

    {{-- The player itself: resources/js/lesson-player.js --}}
</x-layouts.app>
