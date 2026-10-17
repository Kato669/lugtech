{{--
    "Say it": record yourself, compare with the recording, then say whether it sounded the same.
    Needs, in the surrounding Alpine scope, the options for sayIt(): include with
    @include('lesson.partials.say-it', ['opts' => "{ phraseId: step.phrase.id, model: step.phrase.audio, lessonId, source: 'learn' }"])
--}}
<div x-data="sayIt({!! $opts !!})" class="say-it">{{-- $opts is fixed template text, never user input --}}
    <p class="text-sm text-bark-800">Say it yourself, then compare.</p>

    <template x-if="!supported">
        <p class="mt-2 text-sm text-bark-500">This browser can't record here. Say it out loud anyway!</p>
    </template>

    <template x-if="supported">
        <div>
            <div class="mt-3 flex flex-wrap justify-center gap-2">
                <button type="button" class="btn-ghost !py-2" @click="toggle()" :class="recording && 'ring-2 ring-red-400'"
                        :aria-label="recording ? 'Stop recording' : 'Record yourself'">
                    <span x-show="!recording" x-text="attempts ? '🎙️ Again' : '🎙️ Record'"></span>
                    <span x-show="recording" class="flex items-center gap-2"><span class="say-it-dot" aria-hidden="true"></span>■ Stop <span class="tabular-nums text-xs text-bark-500" x-text="seconds + 's'"></span></span>
                </button>
                <button type="button" class="btn-ghost !py-2" x-show="phase === 'recorded'" @click="playMine()">▶ My voice</button>
                <button type="button" class="btn-ghost !py-2" x-show="phase === 'recorded' && model" @click="compare()">🔁 Compare</button>
            </div>
            <div class="mx-auto mt-2 h-1.5 max-w-48 overflow-hidden rounded-full bg-bark-100" x-show="recording" aria-hidden="true">
                <div class="h-full bg-red-400 transition-all duration-200" :style="`width: ${Math.min(100, elapsed / 70)}%`"></div>
            </div>

            {{-- Automatic check (only when switched on) --}}
            <div class="mt-3" x-show="checking && phase === 'recorded'" x-cloak>
                <p class="text-sm text-bark-500" x-show="busy">👂 Listening to you…</p>
                <template x-if="!busy && result && result.verdict">
                    <div class="mx-auto max-w-sm rounded-xl bg-white/70 px-3 py-2 text-sm" role="status">
                        <span class="badge px-2 py-0.5 text-xs font-bold"
                              :class="{ 'bg-leaf-100 text-leaf-600': result.verdict === 'great', 'bg-gold-300/60 text-bark-800': result.verdict === 'close', 'bg-red-50 text-red-700': ['again', 'silent'].includes(result.verdict) }"
                              x-text="verdictText"></span>
                        <span x-show="result.heard" class="block mt-1 text-bark-800">We heard: “<span class="font-semibold" x-text="result.heard"></span>”</span>
                    </div>
                </template>
                <p class="text-sm text-bark-500" x-show="!busy && result && result.message" x-text="result?.message"></p>
            </div>

            {{-- Your own verdict --}}
            <div class="mt-3" x-show="phase === 'recorded' && !rated">
                <p class="text-sm font-semibold text-bark-800">Did it sound the same?</p>
                <div class="mt-2 flex justify-center gap-2">
                    <button type="button" class="btn-leaf !py-2" @click="rate('good')">👍 Yes, the same</button>
                    <button type="button" class="btn-ghost !py-2" @click="rate('again')">🔁 Not yet</button>
                </div>
            </div>
            <p class="mt-3 text-sm font-semibold text-leaf-600" x-show="rated === 'good'" role="status">Great! Say it again whenever you like.</p>
            <p class="mt-3 text-sm text-bark-800" x-show="rated === 'again' && phase === 'ready'" role="status">No problem. Listen once more, then try again.</p>

            <p class="mt-2 text-sm text-red-600" x-show="error" x-text="error"></p>
            <p class="mt-3 text-xs text-bark-500" x-show="checking" x-cloak>
                Your recording is sent to <span x-text="checker"></span> to check it, and is not kept. Automatic checks for Luganda can be wrong: trust your ears too.
            </p>
        </div>
    </template>
</div>
