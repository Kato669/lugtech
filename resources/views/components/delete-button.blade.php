@props(['action'])
<form method="POST" action="{{ $action }}" onsubmit="return confirm('Delete this permanently?')" class="inline">
    @csrf @method('DELETE')
    <button class="text-sm text-red-600 hover:underline">Delete</button>
</form>
