{{-- Mukasa: a young man in a kanzu (white robe with the maroon omulela at the neck). Original character.
     Moods (idle, speaking, thinking, happy, pointing…) come from CSS on a wrapper: see components/character. --}}
<svg {{ $attributes->merge(['class' => 'talker']) }} viewBox="0 0 120 210" fill="none" role="img" aria-label="Mukasa">
    <ellipse class="char-shadow" cx="60" cy="203" rx="34" ry="5" fill="#3b1f12" opacity=".15"/>
    <g class="char-body">
        {{-- kanzu --}}
        <path d="M34 92c-8 20-12 60-14 106h80c-2-46-6-86-14-106-8-6-18-9-26-9s-18 3-26 9z" fill="#fbf6ec" stroke="#3b1f12" stroke-width="3" stroke-linejoin="round"/>
        <path d="M60 86v74" stroke="#8b2a1f" stroke-width="5" stroke-linecap="round"/>
        <path d="M53 88q7 8 14 0" stroke="#8b2a1f" stroke-width="3" fill="none"/>
        <path d="M57 100h6M57 112h6M57 124h6M57 136h6" stroke="#f4cf6b" stroke-width="1.5"/>
        {{-- arms: each turns at the shoulder --}}
        <g class="talker-arm arm-left" style="transform-origin: 37px 96px">
            <path d="M36 96c-10 18-12 36-8 54" stroke="#3b1f12" stroke-width="3" fill="#fbf6ec"/>
            <circle cx="28" cy="152" r="6" fill="#7a4a2b" stroke="#3b1f12" stroke-width="2.5"/>
        </g>
        <g class="arm-right" style="transform-origin: 83px 96px">
            <path d="M84 96c10 18 12 36 8 54" stroke="#3b1f12" stroke-width="3" fill="#fbf6ec"/>
            <circle cx="92" cy="152" r="6" fill="#7a4a2b" stroke="#3b1f12" stroke-width="2.5"/>
            <path class="point-finger" d="M92 152l2 9" stroke="#7a4a2b" stroke-width="4" stroke-linecap="round"/>
        </g>
        {{-- neck + head --}}
        <path d="M53 78h14v10H53z" fill="#7a4a2b"/>
        <g class="talker-head">
            <ellipse cx="60" cy="50" rx="27" ry="30" fill="#7a4a2b" stroke="#3b1f12" stroke-width="3"/>
            <path d="M34 42c0-18 12-26 26-26s26 8 26 26c-6-8-16-11-26-11s-20 3-26 11z" fill="#1f130b"/>
            <ellipse cx="33" cy="52" rx="4" ry="6" fill="#7a4a2b" stroke="#3b1f12" stroke-width="2.5"/>
            <ellipse cx="87" cy="52" rx="4" ry="6" fill="#7a4a2b" stroke="#3b1f12" stroke-width="2.5"/>
            <g class="talker-eyes">
                <ellipse cx="49" cy="51" rx="3.5" ry="4.5" fill="#1f130b"/><circle cx="50.5" cy="49.5" r="1.2" fill="#fff"/>
                <ellipse cx="71" cy="51" rx="3.5" ry="4.5" fill="#1f130b"/><circle cx="72.5" cy="49.5" r="1.2" fill="#fff"/>
            </g>
            <g class="brows"><path d="M44 43q5-3 10 0M66 43q5-3 10 0" stroke="#1f130b" stroke-width="2.5" stroke-linecap="round"/></g>
            <ellipse cx="42" cy="61" rx="4.5" ry="2.5" fill="#c0603a" opacity=".45"/>
            <ellipse cx="78" cy="61" rx="4.5" ry="2.5" fill="#c0603a" opacity=".45"/>
            <path class="mouth-closed" d="M52 65q8 7 16 0" stroke="#1f130b" stroke-width="3" stroke-linecap="round"/>
            <ellipse class="mouth-open" cx="60" cy="67" rx="6" ry="5" fill="#5a1a10" stroke="#1f130b" stroke-width="2"/>
            <path class="mouth-happy" d="M50 63q10 13 20 0z" fill="#5a1a10" stroke="#1f130b" stroke-width="2.5" stroke-linejoin="round"/>
            <path class="mouth-sad" d="M52 70q8-6 16 0" stroke="#1f130b" stroke-width="3" stroke-linecap="round"/>
            <path class="mouth-flat" d="M54 67h12" stroke="#1f130b" stroke-width="3" stroke-linecap="round"/>
        </g>
        {{-- extras shown by some moods --}}
        <g class="think-dots" fill="#fbf6ec" stroke="#3b1f12" stroke-width="2">
            <circle cx="92" cy="22" r="3"/><circle cx="101" cy="13" r="4"/><circle cx="112" cy="3" r="5"/>
        </g>
        <g class="sparkles" fill="#f4cf6b" stroke="#3b1f12" stroke-width="1.5">
            <path d="M18 18l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/><path d="M100 30l1.5 4 4 1.5-4 1.5-1.5 4-1.5-4-4-1.5 4-1.5z"/>
        </g>
    </g>
</svg>
