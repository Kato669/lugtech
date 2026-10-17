{{-- Amadinda: log xylophone --}}
<svg {{ $attributes }} viewBox="0 0 160 110" fill="none" aria-hidden="true">
    <path d="M10 40l140 -10M14 90l140 -10" stroke="#3e7c3a" stroke-width="7" stroke-linecap="round"/>
    @foreach (range(0, 7) as $i)
        <rect x="{{ 18 + $i * 17 }}" y="{{ 18 + $i * 1.5 }}" width="12" height="{{ 84 - $i * 6 }}" rx="5" fill="{{ $i % 2 ? '#c9773f' : '#a65a33' }}" stroke="#3b1f12" stroke-width="2"/>
    @endforeach
</svg>
