<x-layouts.app title="Recording script">
    <div class="flex flex-wrap items-end justify-between gap-4 print:hidden">
        <div>
            <h1 class="text-3xl font-bold">Recording script</h1>
            <p class="mt-1 max-w-2xl text-bark-800">For speakers recording on their own phone or in a studio. Save each recording with its <b>code</b> at the start of the file name, then drop them all into <a href="{{ route('studio.upload') }}" class="font-semibold text-bark-600 underline">Bulk upload</a>.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="?{{ $missing ? '' : 'missing=1' }}" class="btn-ghost !py-2 text-sm">{{ $missing ? 'Show everything' : 'Only missing audio' }}</a>
            <a href="?format=csv{{ $missing ? '&missing=1' : '' }}" class="btn-ghost !py-2 text-sm">Download CSV</a>
            <button onclick="window.print()" class="btn-primary !py-2 text-sm">Print</button>
        </div>
    </div>

    <div class="card mt-6 !p-5 text-sm print:border-0 print:p-0 print:shadow-none">
        <p class="font-display text-lg font-bold">How to name files</p>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            <p><span class="rounded bg-bark-50 px-2 py-0.5 font-mono">P0007-oli-otya.mp3</span> normal speed</p>
            <p><span class="rounded bg-bark-50 px-2 py-0.5 font-mono">P0007-oli-otya-slow.mp3</span> slow version</p>
            <p><span class="rounded bg-bark-50 px-2 py-0.5 font-mono">P0001-oli-otya-mukasa.mp3</span> Mukasa's voice (a man)</p>
            <p><span class="rounded bg-bark-50 px-2 py-0.5 font-mono">P0002-bulungi-nakato.mp3</span> Nakato's voice (a woman)</p>
        </div>
        <p class="mt-2 text-bark-500">Only the code matters (<span class="font-mono">P0007</span>, <span class="font-mono">p7</span> and <span class="font-mono">P 0007</span> all work). Add “slow” anywhere after the code for the slow take, and “mukasa” or “nakato” for a character voice (only lines marked with their name). Any audio format is fine: mp3, m4a (iPhone), ogg/opus (WhatsApp), wav.</p>
    </div>

    <p class="mt-6 text-sm text-bark-500 print:mt-2">{{ $count }} items{{ $missing ? ' still need a recording' : '' }} · printed {{ now()->format('j M Y') }}</p>

    @foreach ($groups as $group => $items)
        <section class="mt-6 break-inside-avoid-page">
            <h2 class="font-display text-xl font-bold">{{ $group }}</h2>
            <table class="mt-2 w-full overflow-hidden rounded-2xl bg-white text-left text-sm print:rounded-none">
                <thead class="bg-bark-50 text-xs uppercase tracking-wide text-bark-800">
                    <tr><th class="w-8 px-3 py-2">✓</th><th class="px-3 py-2">Code</th><th class="px-3 py-2">Luganda</th><th class="px-3 py-2">English</th><th class="px-3 py-2 print:hidden">Audio</th></tr>
                </thead>
                <tbody>
                    @foreach ($items as $i)
                        <tr class="break-inside-avoid border-t border-bark-100 align-top">
                            <td class="px-3 py-2"><span class="inline-block h-4 w-4 rounded border-2 border-bark-500"></span></td>
                            <td class="px-3 py-2 font-mono font-bold">{{ $i['code'] }}</td>
                            <td class="px-3 py-2">
                                <span class="font-display text-base font-bold">{{ $i['luganda'] }}</span>
                                @if ($i['has_slow'])<span class="block text-xs text-bark-500">record normal + slow</span>@endif
                                @if ($i['voice'])<span class="mt-0.5 block text-xs font-bold text-bark-800">+ {{ $i['voice'] === 'male' ? 'Mukasa (a man): …-mukasa' : 'Nakato (a woman): …-nakato' }}</span>@endif
                            </td>
                            <td class="px-3 py-2">{{ $i['english'] }}@if ($i['notes'])<span class="block text-xs text-bark-500">{{ $i['notes'] }}</span>@endif</td>
                            <td class="px-3 py-2 text-xs print:hidden">
                                <span class="{{ $i['audio'] ? 'text-leaf-600' : 'text-bark-500' }}">{{ $i['audio'] ? '✓ normal' : '– normal' }}</span>
                                @if ($i['has_slow'])<span class="block {{ $i['audio_slow'] ? 'text-leaf-600' : 'text-bark-500' }}">{{ $i['audio_slow'] ? '✓ slow' : '– slow' }}</span>@endif
                                @if ($i['voice'])@php $has = $i['voice'] === 'male' ? $i['audio_male'] : $i['audio_female']; $who = $i['voice'] === 'male' ? 'Mukasa' : 'Nakato'; @endphp<span class="block {{ $has ? 'text-leaf-600' : 'text-bark-500' }}">{{ $has ? '✓' : '–' }} {{ $who }}</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach

    <style>
        @media print {
            header, footer { display: none !important; }
            body { background: #fff !important; }
            main { padding: 0 !important; max-width: none !important; }
        }
    </style>
</x-layouts.app>
