{{-- PATTERN: a "How it works" grammar card (Phase 16). Columns side by side, the pattern highlighted --}}
<template x-if="step.type === 'pattern'">
    <div>
        <p class="text-center text-sm font-semibold uppercase tracking-wide text-leaf-600">How it works</p>
        <h2 class="mt-1 text-center font-display text-2xl font-bold text-bark-900" x-show="step.title" x-text="step.title"></h2>
        <table class="pattern-table mt-4 w-full table-fixed border-separate border-spacing-x-2 border-spacing-y-2">
            <caption class="sr-only" x-text="step.title || 'How the words change'"></caption>
            <template x-if="step.heads">
                <thead>
                    <tr>
                        <template x-for="(head, c) in step.heads" :key="'h' + c">
                            <th scope="col" class="pb-1 text-center text-sm font-bold uppercase tracking-wide text-bark-600" x-text="head"></th>
                        </template>
                    </tr>
                </thead>
            </template>
            <tbody>
                <template x-for="(row, r) in step.rows" :key="'r' + r">
                    <tr>
                        <template x-for="(cell, c) in row" :key="'c' + r + '-' + c">
                            <td class="rounded-2xl bg-bark-50 px-2 py-2 text-center align-top">
                                <div class="pattern-pic" x-show="cell.picture" x-html="cell.picture ? lugPicture(cell.picture.name, cell.picture.count) : ''"></div>
                                <p class="font-display text-xl font-bold leading-tight text-bark-900 sm:text-2xl" lang="lg">
                                    <template x-for="(part, i) in cell.parts" :key="i"><span :class="part.m && 'pattern-mark'" x-text="part.t"></span></template>
                                </p>
                                <p class="mt-0.5 text-sm text-bark-800" x-text="cell.english"></p>
                                @if ($voiceOn)
                                    <button type="button" class="speak-btn mt-1" x-show="cell.audio" @click="playAudio(cell.audio)" :aria-label="'Listen: ' + cell.luganda">🔊</button>
                                @endif
                            </td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>
        <p class="mx-auto mt-3 max-w-md rounded-xl bg-gold-300/30 px-4 py-3 text-sm text-bark-800" x-show="step.note" x-text="step.note"></p>
    </div>
</template>
