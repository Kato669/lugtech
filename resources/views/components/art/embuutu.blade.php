{{-- Embuutu: big bass drum --}}
<svg {{ $attributes }} viewBox="0 0 140 120" fill="none" aria-hidden="true">
    <path d="M14 30c0 40 14 70 26 82h60c12-12 26-42 26-82" fill="#a65a33" stroke="#3b1f12" stroke-width="3" stroke-linejoin="round"/>
    @foreach (range(0, 7) as $i)
        <path d="M{{ 20 + $i * 14 }} 36l{{ $i < 4 ? 8 : -8 }} 40" stroke="#f4cf6b" stroke-width="2.2" stroke-linecap="round"/>
    @endforeach
    <path d="M24 82c30 8 62 8 92 0" stroke="#5a2f1b" stroke-width="5" stroke-linecap="round"/>
    <ellipse cx="70" cy="30" rx="56" ry="17" fill="#f4cf6b" stroke="#3b1f12" stroke-width="3"/>
    <ellipse cx="70" cy="28" rx="40" ry="10" fill="#f8dd8f"/>
</svg>
