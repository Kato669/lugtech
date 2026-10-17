@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null])
<div>
    <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="3" {{ $attributes->merge(['class' => 'field']) }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" {{ $attributes->merge(['class' => 'field']) }}>
    @endif
    @if ($help)<p class="mt-1 text-xs text-bark-500">{{ $help }}</p>@endif
    @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
