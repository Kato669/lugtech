{{--
    A teaching character with moods: <x-character name="mukasa" state="teacherState" />
    name:  mukasa | nakato | ngoma
    state: an Alpine expression giving one of
           idle · speaking · listening · thinking · happy · sad · correct · wrong · celebrating · pointing
    The same drawing everywhere (lessons, conversations, home page); only CSS changes per mood.
--}}
@props(['name' => 'mukasa', 'state' => "'idle'"])
<span {{ $attributes->merge(['class' => 'char char-'.$name]) }} :class="'is-' + ({{ $state }})" data-character="{{ $name }}">
    @if ($name === 'ngoma')
        <x-art.ngoma mood="auto" class="h-full w-full" />
    @elseif ($name === 'nakato')
        <x-art.nakato class="h-full w-full" />
    @else
        <x-art.mukasa class="h-full w-full" />
    @endif
</span>
