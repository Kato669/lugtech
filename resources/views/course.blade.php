<x-layouts.app title="Learn">
    @php
        $stageIcons = ['🏡', '🍌', '🧺', '🛵', '📚', '🥁', '🌊'];
        $offsets = ['translate-x-0', 'translate-x-16', 'translate-x-24', 'translate-x-16', 'translate-x-0', '-translate-x-16', '-translate-x-24', '-translate-x-16'];
        $me = auth()->user();
    @endphp
    <div class="grid gap-8 lg:grid-cols-[1fr_19rem]">
        <div class="min-w-0">
            <div class="flex items-center gap-4">
                <x-art.ngoma class="anim-bob h-20 w-20 shrink-0" />
                <div>
                    <h1 class="text-4xl font-bold">{{ $me->is_guest ? 'Oli otya!' : 'Oli otya, '.\Illuminate\Support\Str::of($me->name)->before(' ').'!' }}</h1>
                    <p class="text-bark-800">{{ $next ? 'Ngoma is waiting on your next lesson.' : 'You have finished every lesson so far. Kulika!' }}</p>
                </div>
            </div>

            @if ($next)
                <a href="{{ route('lessons.show', $next) }}" class="group mt-6 flex items-center justify-between gap-4 rounded-3xl bg-bark-600 p-5 text-white shadow-[0_6px_0_#5a2f1b] transition hover:-translate-y-0.5 active:translate-y-1 active:shadow-none">
                    <span>
                        <span class="text-sm font-semibold opacity-80">Up next</span>
                        <span class="block font-display text-2xl font-bold">{{ $next->title }}</span>
                    </span>
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white/20 text-2xl transition group-hover:translate-x-1">→</span>
                </a>
            @endif

            <div class="mt-10 space-y-12">
                @foreach ($stages as $s => $stage)
                    <section>
                        <div class="barkcloth flex items-center gap-4 rounded-3xl border-4 border-bark-800 px-5 py-4 text-white">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-cream text-3xl">{{ $stageIcons[$s] ?? '⭐' }}</span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wider text-gold-300">Stage {{ $s + 1 }}</p>
                                <h2 class="truncate text-2xl font-bold">{{ $stage->title_lg }} <span class="font-normal text-bark-50">· {{ $stage->title_en }}</span></h2>
                            </div>
                            @if ($stage->greeting)<span class="ml-auto hidden rounded-full bg-bark-900/40 px-3 py-1 text-sm font-bold text-gold-300 sm:block">{{ $stage->greeting }}</span>@endif
                        </div>

                        @if ($stage->publishedLessons->isNotEmpty())
                            {{-- Keep the whole stage on this phone: lessons and recordings (resources/js/offline.js) --}}
                            <div class="mt-3 flex justify-end" x-data="saveStage(@js(route('offline.stage', $stage)))" x-show="supported" x-cloak>
                                <button type="button" class="btn-ghost !px-3 !py-1.5 text-sm" @click="save()" x-show="state === 'idle' || state === 'error'">⬇ Save for offline</button>
                                <p class="text-sm font-semibold text-bark-800" x-show="state === 'saving'" role="status">Saving… <span x-text="total ? Math.round(done / total * 100) + '%' : ''"></span></p>
                                <p class="flex items-center gap-2 text-sm font-semibold text-leaf-600" x-show="state === 'saved'" role="status">✓ Saved: works without a signal<span x-show="failed"> (<span x-text="failed"></span> not saved)</span>
                                    <button type="button" class="font-normal text-bark-500 underline" @click="save()" aria-label="Save this stage again, with any new lessons">Update</button></p>
                                <p class="text-sm text-red-600" x-show="state === 'error'">Couldn't save right now.</p>
                            </div>
                        @endif

                        @if ($stage->publishedLessons->isEmpty())
                            <div class="mt-6 flex items-center justify-center gap-3 text-bark-500">
                                <x-art.spear class="h-4 w-16 opacity-60" /> Lessons coming soon <x-art.spear class="h-4 w-16 -scale-x-100 opacity-60" />
                            </div>
                        @else
                            {{-- Winding trail of lessons --}}
                            <ol class="mt-6 flex flex-col items-center gap-5">
                                @foreach ($stage->publishedLessons as $l => $lesson)
                                    @php
                                        $score = $completed->get($lesson->id);
                                        $isNext = $next?->is($lesson);
                                    @endphp
                                    <li class="relative {{ $offsets[$l % count($offsets)] }}">
                                        @if ($isNext)
                                            <x-art.ngoma class="anim-bob pointer-events-none absolute -left-24 top-0 hidden h-20 w-20 sm:block" />
                                        @endif
                                        <a href="{{ route('lessons.show', $lesson) }}" class="group flex flex-col items-center" title="{{ $lesson->title }}">
                                            <span @class([
                                                'relative flex h-20 w-20 items-center justify-center rounded-full border-4 text-3xl font-bold transition group-hover:-translate-y-1 group-active:translate-y-1',
                                                'border-gold-500 bg-gold-300 text-bark-800 shadow-[0_6px_0_#b9831a]' => $score !== null,
                                                'anim-beat border-bark-800 bg-bark-600 text-white shadow-[0_6px_0_#3b1f12]' => $isNext,
                                                'border-bark-100 bg-white text-bark-500 shadow-[0_6px_0_#efd9c8]' => $score === null && ! $isNext,
                                            ])>
                                                {{ $score !== null ? '★' : ($isNext ? '▶' : $l + 1) }}
                                            </span>
                                            <span class="mt-2 max-w-40 text-center text-sm font-bold leading-tight {{ $isNext ? 'text-bark-900' : 'text-bark-800' }}">{{ $lesson->title }}</span>
                                            @if ($lesson->isScripted())<span class="badge mt-1 bg-leaf-100 text-leaf-600" title="Taught by Mukasa and Nakato, with sound and games">🎬 Interactive</span>@endif
                                            @if ($score !== null)<span class="text-xs text-bark-500">Best {{ $score }}%</span>@endif
                                        </a>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </section>
                @endforeach
            </div>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            {{-- Install the app (Android/desktop: one tap; iPhone/iPad: Share → Add to Home Screen) --}}
            <div x-data="installApp" x-cloak x-show="!installed && (canInstall || ios)" class="card border-gold-500/50 bg-gold-300/20">
                <p class="font-display text-lg font-bold">📲 Put Luganda on your phone</p>
                <p class="mt-1 text-sm text-bark-800">Open it like an app, and keep learning without a signal.</p>
                <button type="button" class="btn-primary mt-3 w-full" x-show="canInstall" @click="install()">Install the app</button>
                <div x-show="!canInstall && ios">
                    <button type="button" class="btn-ghost mt-3 w-full" @click="showIos = !showIos">How to install on iPhone</button>
                    <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-bark-800" x-show="showIos">
                        <li>Open this page in <b>Safari</b>.</li>
                        <li>Tap the <b>Share</b> button (the square with an arrow).</li>
                        <li>Choose <b>Add to Home Screen</b>, then <b>Add</b>.</li>
                    </ol>
                </div>
            </div>
            @if ($me->is_guest && $completed->isNotEmpty())
                <div class="card border-leaf-500/40 bg-leaf-100/60">
                    <p class="font-display text-lg font-bold text-leaf-600">Keep your progress</p>
                    <p class="mt-1 text-sm text-bark-800">You're learning as a guest. Create a free account to keep your stars and cowries on every device.</p>
                    <a href="{{ route('register') }}" class="btn-leaf mt-3 w-full">Save my progress</a>
                </div>
            @endif
            <div class="card">
                <p class="text-sm font-bold uppercase tracking-wide text-bark-500">Review</p>
                <p class="mt-1 font-display text-3xl font-bold">{{ $dueCount }} <span class="text-base font-normal">phrases due</span></p>
                @if ($dueCount)
                    <a href="{{ route('review') }}" class="btn-leaf mt-3 w-full">Review now</a>
                @else
                    <p class="mt-3 text-sm text-bark-800">Nothing due right now. Finished phrases come back here to review.</p>
                @endif
            </div>
            <a href="{{ route('games.index') }}" class="card flex items-center gap-4 !p-4 transition hover:-translate-y-0.5">
                <x-art.engalabi class="h-14 w-6 shrink-0" />
                <span><span class="block font-display text-lg font-bold">Games</span><span class="text-sm text-bark-500">Memory match, the talking drum{{ $voiceOn ? ' and Say it' : '' }}</span></span>
            </a>
            @if ($wordOfDay)
                <div class="card">
                    <p class="text-sm font-bold uppercase tracking-wide text-bark-500">Word of the day</p>
                    <p class="mt-1 font-display text-2xl font-bold">{{ $wordOfDay->luganda }} <x-speak :item="$wordOfDay" /></p>
                    <p class="text-sm">{{ $wordOfDay->english }}</p>
                </div>
            @endif
            @if ($proverb)
                <div class="card">
                    <p class="text-sm font-bold uppercase tracking-wide text-bark-500">Olugero</p>
                    <p class="mt-1 font-display text-lg font-bold italic">“{{ $proverb->luganda }}”</p>
                    <p class="text-sm">{{ $proverb->english }}</p>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.app>
