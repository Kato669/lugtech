{{--
    A teaching character with moods: <x-character name="mukasa" state="teacherState" />
    name:  mukasa | nakato | ngoma
    state: an Alpine expression giving one of
           idle · speaking · listening · thinking · happy · sad · correct · wrong · celebrating · pointing
    The same drawing everywhere (lessons, conversations, home page); only CSS changes per mood.
--}}
@props(['name' => 'mukasa', 'state' => "'idle'", 'look' => null])
{{-- Phase 13: dressed as this learner chose (robe colour, cap, beads, sash, drum: App\Services\Wardrobe). $look overrides, e.g. for previews. --}}
@php($dress = $look ?? app(\App\Services\Wardrobe::class)->look(auth()->user(), $name))
<span {{ $attributes->merge(['class' => trim('char char-'.$name.' '.$dress['class'])]) }} @if ($dress['style']) style="{{ $dress['style'] }}" @endif :class="'is-' + ({{ $state }})" data-character="{{ $name }}">
    @if ($name === 'ngoma')
        <x-art.ngoma mood="auto" class="h-full w-full" />
    @elseif ($name === 'nakato')
        <x-art.nakato class="h-full w-full" />
    @else
        <x-art.mukasa class="h-full w-full" />
    @endif
</span>
