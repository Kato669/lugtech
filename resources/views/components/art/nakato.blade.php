{{-- Nakato: a young woman in a gomesi (puffed, pointed shoulders and a sash at the waist). Original character.
     Moods (idle, speaking, thinking, happy, pointing…) come from CSS on a wrapper: see components/character. --}}
<svg {{ $attributes->merge(['class' => 'talker']) }} viewBox="0 0 120 210" fill="none" role="img" aria-label="Nakato">
    <ellipse class="char-shadow" cx="60" cy="203" rx="38" ry="5" fill="#3b1f12" opacity=".15"/>
    <g class="char-body">
        {{-- gomesi --}}
        <path d="M38 92c-6 24-16 66-20 106h84c-4-40-14-82-20-106-7-5-14-8-22-8s-15 3-22 8z" fill="var(--robe, #3e7c3a)" stroke="#3b1f12" stroke-width="3" stroke-linejoin="round"/>
        <path d="M30 168c20 6 40 6 60 0M26 186c22 6 46 6 68 0" stroke="var(--robe-dark, #2f652c)" stroke-width="2" opacity=".7"/>
        {{-- arms: each turns at the shoulder (drawn under the puffed sleeves) --}}
        <g class="talker-arm arm-left" style="transform-origin: 38px 102px">
            <path d="M38 104c-8 14-10 28-8 40" stroke="#7a4a2b" stroke-width="7" stroke-linecap="round"/>
        </g>
        <g class="arm-right" style="transform-origin: 82px 102px">
            <path d="M82 104c8 14 10 28 8 40" stroke="#7a4a2b" stroke-width="7" stroke-linecap="round"/>
            <path class="point-finger" d="M90 144l1 7" stroke="#7a4a2b" stroke-width="4" stroke-linecap="round"/>
            <g class="item item-drum">
                <path d="M90 142h16l-3 16h-10z" fill="#9a5a32" stroke="#3b1f12" stroke-width="2" stroke-linejoin="round"/>
                <ellipse cx="98" cy="142" rx="8" ry="3" fill="#f4e3c3" stroke="#3b1f12" stroke-width="2"/>
                <path d="M92 147l3 9M98 147v10M104 147l-3 9" stroke="#f4e3c3" stroke-width="1"/>
            </g>
        </g>
        {{-- puffed shoulders --}}
        <path d="M40 90c-10-4-20 0-22 10 6-2 12 2 20 4z" fill="var(--robe-light, #4f9348)" stroke="#3b1f12" stroke-width="2.5" stroke-linejoin="round"/>
        <path d="M80 90c10-4 20 0 22 10-6-2-12 2-20 4z" fill="var(--robe-light, #4f9348)" stroke="#3b1f12" stroke-width="2.5" stroke-linejoin="round"/>
        {{-- square neckline --}}
        <path d="M50 88v10h20V88" fill="#7a4a2b" stroke="#3b1f12" stroke-width="2"/>
        {{-- sash (kitambaala) --}}
        <path d="M30 128c20 6 40 6 60 0l2 12c-22 6-42 6-64 0z" fill="var(--sash, #e0a526)" stroke="#3b1f12" stroke-width="2.5"/>
        <path d="M70 136l8 20-6 2-6-18z" fill="var(--sash, #e0a526)" stroke="#3b1f12" stroke-width="2"/>
        {{-- Phase 13 wardrobe: shown only when worn (classes wear-beads, wear-sash…: components/character) --}}
        <g class="item item-sash">
            <path d="M37 92L47 100L87 152L77 144z" fill="#9a5a32" stroke="#3b1f12" stroke-width="2" stroke-linejoin="round"/>
            <path d="M44 102L50 98M60 125L66 121M76 149L82 145" stroke="#d9a066" stroke-width="1.5"/>
        </g>
        <g class="item item-beads"><circle cx="51.0" cy="89.0" r="2.3" fill="#e0a526" stroke="#3b1f12" stroke-width="1"/><circle cx="53.2" cy="93.4" r="2.3" fill="#8b2a1f" stroke="#3b1f12" stroke-width="1"/><circle cx="56.0" cy="96.2" r="2.3" fill="#2f6fa8" stroke="#3b1f12" stroke-width="1"/><circle cx="58.6" cy="97.0" r="2.3" fill="#e0a526" stroke="#3b1f12" stroke-width="1"/><circle cx="61.4" cy="97.0" r="2.3" fill="#8b2a1f" stroke="#3b1f12" stroke-width="1"/><circle cx="64.0" cy="96.2" r="2.3" fill="#2f6fa8" stroke="#3b1f12" stroke-width="1"/><circle cx="66.8" cy="93.4" r="2.3" fill="#e0a526" stroke="#3b1f12" stroke-width="1"/><circle cx="69.0" cy="89.0" r="2.3" fill="#8b2a1f" stroke="#3b1f12" stroke-width="1"/></g>
        {{-- neck + head --}}
        <path d="M54 76h12v12H54z" fill="#7a4a2b"/>
        <g class="talker-head">
            <circle cx="60" cy="20" r="11" fill="#1f130b"/>
            <ellipse cx="60" cy="50" rx="25" ry="29" fill="#7a4a2b" stroke="#3b1f12" stroke-width="3"/>
            <path d="M35 46c0-18 11-27 25-27s25 9 25 27c-4-10-14-14-25-14s-21 4-25 14z" fill="#1f130b"/>
            <circle cx="34" cy="60" r="3" fill="#e0a526" stroke="#3b1f12" stroke-width="1.5"/>
            <circle cx="86" cy="60" r="3" fill="#e0a526" stroke="#3b1f12" stroke-width="1.5"/>
            <g class="talker-eyes">
                <ellipse cx="50" cy="52" rx="3.5" ry="4.5" fill="#1f130b"/><circle cx="51.5" cy="50.5" r="1.2" fill="#fff"/>
                <ellipse cx="70" cy="52" rx="3.5" ry="4.5" fill="#1f130b"/><circle cx="71.5" cy="50.5" r="1.2" fill="#fff"/>
                <path d="M45 47l-3-2M75 47l3-2" stroke="#1f130b" stroke-width="1.8" stroke-linecap="round"/>
            </g>
            <g class="brows"><path d="M46 44q4-2 8 0M66 44q4-2 8 0" stroke="#1f130b" stroke-width="2" stroke-linecap="round"/></g>
            <ellipse cx="43" cy="62" rx="4.5" ry="2.5" fill="#d0704a" opacity=".5"/>
            <ellipse cx="77" cy="62" rx="4.5" ry="2.5" fill="#d0704a" opacity=".5"/>
            <path class="mouth-closed" d="M53 66q7 6 14 0" stroke="#1f130b" stroke-width="3" stroke-linecap="round"/>
            <ellipse class="mouth-open" cx="60" cy="68" rx="5.5" ry="4.5" fill="#5a1a10" stroke="#1f130b" stroke-width="2"/>
            <path class="mouth-happy" d="M51 64q9 12 18 0z" fill="#5a1a10" stroke="#1f130b" stroke-width="2.5" stroke-linejoin="round"/>
            <path class="mouth-sad" d="M53 70q7-5 14 0" stroke="#1f130b" stroke-width="3" stroke-linecap="round"/>
            <path class="mouth-flat" d="M55 67h10" stroke="#1f130b" stroke-width="3" stroke-linecap="round"/>
        </g>
        <g class="think-dots" fill="#fbf6ec" stroke="#3b1f12" stroke-width="2">
            <circle cx="90" cy="24" r="3"/><circle cx="99" cy="15" r="4"/><circle cx="110" cy="5" r="5"/>
        </g>
        <g class="sparkles" fill="#f4cf6b" stroke="#3b1f12" stroke-width="1.5">
            <path d="M16 22l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/><path d="M100 34l1.5 4 4 1.5-4 1.5-1.5 4-1.5-4-4-1.5 4-1.5z"/>
        </g>
    </g>
</svg>
