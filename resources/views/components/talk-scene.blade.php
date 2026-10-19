{{--
    Two characters having a short conversation. Bind it to Alpine data from talkScene(lines):
    lines = [{ who: 0|1, luganda, english, audio }]
--}}
<div {{ $attributes->merge(['class' => 'relative']) }}>
    <div class="relative grid grid-cols-2 items-end gap-2 rounded-3xl bg-gradient-to-b from-gold-300/30 to-leaf-100/60 px-3 pt-24 pb-2">
        {{-- ground --}}
        <div class="absolute inset-x-0 bottom-0 h-8 rounded-b-3xl bg-leaf-500/20"></div>
        @foreach ([0 => 'mukasa', 1 => 'nakato'] as $who => $art)
            <div class="relative flex flex-col items-center">
                {{-- speech bubble: the latest thing this person said --}}
                <div x-show="bubbleFor({{ $who }})" x-transition:enter="anim-pop"
                     class="absolute bottom-full z-10 mb-1 w-max max-w-[11rem] rounded-2xl border-2 border-bark-900 bg-white px-3 py-1.5 text-center shadow-[0_3px_0_#3b1f12] {{ $who === 0 ? 'left-1' : 'right-1' }}"
                     :class="speaking === {{ $who }} ? 'ring-4 ring-gold-300' : ''">
                    <p class="font-display text-lg font-bold leading-tight text-bark-900" x-lug="bubbleFor({{ $who }})?.luganda"></p>
                    <p class="text-xs text-bark-500" x-show="showMeaning" x-text="bubbleFor({{ $who }})?.english"></p>
                    <span class="absolute -bottom-2 h-3 w-3 rotate-45 border-b-2 border-r-2 border-bark-900 bg-white {{ $who === 0 ? 'left-8' : 'right-8' }}"></span>
                </div>
                <button type="button" @click="say({{ $who }})" aria-label="Tap {{ ucfirst($art) }} to hear {{ $who === 0 ? 'him' : 'her' }}"
                        class="group cursor-pointer rounded-2xl transition-transform duration-300 hover:-translate-y-1 focus-visible:outline-4 focus-visible:outline-gold-300 active:scale-95"
                        :class="speaking === {{ $who }} && 'is-talking -translate-y-1'">
                    <x-character :name="$art" state="sceneState({{ $who }})" class="h-40 w-24" />
                </button>
                <span class="mt-1 rounded-full bg-white/80 px-2 text-xs font-bold text-bark-800">{{ ucfirst($art) }}</span>
            </div>
        @endforeach
    </div>
    <p class="mt-2 text-center text-xs font-semibold text-bark-500">{{ $voiceOn ? 'Tap Mukasa or Nakato to hear them.' : 'Tap Mukasa or Nakato to see what they say.' }}</p>
    <button type="button" @click="play()" :disabled="playing"
        class="absolute top-3 left-1/2 -translate-x-1/2 rounded-full bg-white px-3 py-1 text-sm font-bold text-bark-800 shadow-[0_3px_0_#efd9c8] transition hover:-translate-y-0.5 disabled:opacity-60">
        <span x-text="playing ? '…' : '▶ Hear it'"></span>
    </button>
</div>
