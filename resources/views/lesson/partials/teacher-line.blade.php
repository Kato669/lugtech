{{-- What the teacher says on this step (step.say, in English), with who says it. --}}
<div class="mx-auto mt-3 flex max-w-md items-end justify-center gap-2" x-show="step.say">
    <div class="h-16 w-10 shrink-0" x-show="step.character === 'nakato'"><x-art.nakato class="h-16 w-10" /></div>
    <div class="h-16 w-10 shrink-0" x-show="step.character !== 'nakato' && step.character !== 'ngoma'"><x-art.mukasa class="h-16 w-10" /></div>
    <div class="h-14 w-14 shrink-0" x-show="step.character === 'ngoma'"><x-art.ngoma class="h-14 w-14" /></div>
    <p class="rounded-2xl rounded-bl-none border-2 border-bark-100 bg-white px-3 py-2 text-left font-semibold text-bark-800" x-text="step.say"></p>
</div>
