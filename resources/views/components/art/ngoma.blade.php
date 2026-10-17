@props(['mood' => 'happy'])
{{-- Ngoma: the app's drum character. Original design: a little engoma with a face. --}}
<svg {{ $attributes->merge(['class' => 'ngoma']) }} viewBox="0 0 140 160" fill="none" role="img" aria-label="Ngoma the drum">
    {{-- shadow --}}
    <ellipse cx="70" cy="152" rx="40" ry="6" fill="#3b1f12" opacity=".15"/>
    {{-- body --}}
    <path d="M24 46c0 34 8 72 22 98h48c14-26 22-64 22-98" fill="#a65a33" stroke="#3b1f12" stroke-width="3" stroke-linejoin="round"/>
    {{-- barkcloth band --}}
    <path d="M27 100c28 8 58 8 86 0l-4 18c-26 7-52 7-78 0z" fill="#c9773f" stroke="#3b1f12" stroke-width="2.5"/>
    <path d="M36 106l6 8M50 109l5 8M64 110l4 8M78 110l3 8M92 108l2 8M104 105l1 7" stroke="#5a2f1b" stroke-width="2" stroke-linecap="round"/>
    {{-- lacing --}}
    <path d="M30 52l12 40M50 54l-8 38M58 55l10 38M82 55l-10 38M90 54l8 38M110 52l-12 40" stroke="#f4cf6b" stroke-width="2.5" stroke-linecap="round" opacity=".9"/>
    {{-- cowrie row --}}
    @foreach ([46, 62, 78, 94] as $x)
        <ellipse cx="{{ $x }}" cy="135" rx="5" ry="3.5" fill="#fbf6ec" stroke="#3b1f12" stroke-width="1.5"/>
        <path d="M{{ $x - 3 }} 135h6" stroke="#3b1f12" stroke-width="1"/>
    @endforeach
    {{-- drum skin --}}
    <ellipse cx="70" cy="46" rx="48" ry="16" fill="#f4cf6b" stroke="#3b1f12" stroke-width="3"/>
    <ellipse cx="70" cy="44" rx="34" ry="9" fill="#f8dd8f"/>
    {{-- face --}}
    <g class="ngoma-face">
        @if ($mood === 'auto')
            {{-- every face, switched by the mood on a wrapper (components/character) --}}
            <g class="face-happy">
                <g class="ngoma-eyes">
                    <ellipse cx="54" cy="72" rx="5" ry="6.5" fill="#3b1f12"/><circle cx="56" cy="69.5" r="1.8" fill="#fff"/>
                    <ellipse cx="86" cy="72" rx="5" ry="6.5" fill="#3b1f12"/><circle cx="88" cy="69.5" r="1.8" fill="#fff"/>
                </g>
                <path d="M58 86q12 12 24 0" stroke="#3b1f12" stroke-width="3.5" stroke-linecap="round" fill="#7a2e16"/>
            </g>
            <g class="face-wow">
                <circle cx="54" cy="72" r="7" fill="#fff" stroke="#3b1f12" stroke-width="2.5"/><circle cx="54" cy="72" r="3.5" fill="#3b1f12"/>
                <circle cx="86" cy="72" r="7" fill="#fff" stroke="#3b1f12" stroke-width="2.5"/><circle cx="86" cy="72" r="3.5" fill="#3b1f12"/>
                <ellipse cx="70" cy="90" rx="6" ry="7" fill="#3b1f12"/>
            </g>
            <g class="face-sad">
                <path d="M47 74q7-5 14 0M79 74q7-5 14 0" stroke="#3b1f12" stroke-width="3.5" stroke-linecap="round"/>
                <path d="M60 93q10-7 20 0" stroke="#3b1f12" stroke-width="3.5" stroke-linecap="round"/>
            </g>
        @elseif ($mood === 'wow')
            <circle cx="54" cy="72" r="7" fill="#fff" stroke="#3b1f12" stroke-width="2.5"/><circle cx="54" cy="72" r="3.5" fill="#3b1f12"/>
            <circle cx="86" cy="72" r="7" fill="#fff" stroke="#3b1f12" stroke-width="2.5"/><circle cx="86" cy="72" r="3.5" fill="#3b1f12"/>
            <ellipse cx="70" cy="90" rx="6" ry="7" fill="#3b1f12"/>
        @elseif ($mood === 'sad')
            <path d="M47 74q7-5 14 0M79 74q7-5 14 0" stroke="#3b1f12" stroke-width="3.5" stroke-linecap="round"/>
            <path d="M60 93q10-7 20 0" stroke="#3b1f12" stroke-width="3.5" stroke-linecap="round"/>
        @else
            <g class="ngoma-eyes">
                <ellipse cx="54" cy="72" rx="5" ry="6.5" fill="#3b1f12"/><circle cx="56" cy="69.5" r="1.8" fill="#fff"/>
                <ellipse cx="86" cy="72" rx="5" ry="6.5" fill="#3b1f12"/><circle cx="88" cy="69.5" r="1.8" fill="#fff"/>
            </g>
            <path d="M58 86q12 12 24 0" stroke="#3b1f12" stroke-width="3.5" stroke-linecap="round" fill="#7a2e16"/>
        @endif
        <ellipse cx="42" cy="84" rx="6" ry="3.5" fill="#e0805a" opacity=".55"/>
        <ellipse cx="98" cy="84" rx="6" ry="3.5" fill="#e0805a" opacity=".55"/>
    </g>
    {{-- drumsticks --}}
    <g class="ngoma-sticks">
        <path d="M118 18l-22 26" stroke="#5a2f1b" stroke-width="4" stroke-linecap="round"/>
        <circle cx="119" cy="16" r="5" fill="#c9773f" stroke="#3b1f12" stroke-width="2"/>
    </g>
</svg>
