{{-- Endongo: bowl lyre --}}
<svg {{ $attributes }} viewBox="0 0 120 130" fill="none" aria-hidden="true">
    <path d="M30 20L40 90M90 20L80 90" stroke="#5a2f1b" stroke-width="5" stroke-linecap="round"/>
    <path d="M24 20h72" stroke="#5a2f1b" stroke-width="6" stroke-linecap="round"/>
    @foreach ([46, 54, 62, 70, 78] as $x)
        <path d="M{{ $x }} 22L{{ 60 + ($x - 62) / 2 }} 92" stroke="#f4cf6b" stroke-width="1.5"/>
    @endforeach
    <path d="M22 88c0 24 18 36 38 36s38-12 38-36z" fill="#a65a33" stroke="#3b1f12" stroke-width="3"/>
    <path d="M22 88h76" stroke="#3b1f12" stroke-width="3"/>
</svg>
