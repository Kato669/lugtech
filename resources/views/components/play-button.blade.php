{{--
    🔊 button for a recording URL (plus 🐢 for a slow recording). Only one recording
    plays at a time; the button shows loading, playing and "couldn't play".
--}}
@props(['url' => null, 'slow' => null, 'label' => null, 'pending' => false, 'generated' => false])
@if ($url)
    <span class="inline-flex items-center gap-1 align-middle" x-data="speaker(@js($url), @js($slow))">
        <button type="button" @click="play()" class="speak-btn" :class="stateClass(url)" :aria-pressed="(state(url) === 'playing').toString()"
                aria-label="{{ $label ? 'Listen: '.$label : 'Listen' }}" title="Listen">
            <span x-show="state(url) !== 'error'" aria-hidden="true">🔊</span><span x-show="state(url) === 'error'" x-cloak aria-hidden="true">⚠️</span>
        </button>
        @if ($slow)
            <button type="button" @click="playSlow()" class="speak-btn" :class="stateClass(slow)" aria-label="{{ $label ? 'Listen slowly: '.$label : 'Listen slowly' }}" title="Slowly">
                <span aria-hidden="true">🐢</span>
            </button>
        @endif
        @if ($pending)
            <span class="badge bg-gold-300/50 text-bark-800" title="Only reviewers hear this until it is approved on the Check page">pending</span>
        @endif
        @if ($generated)
            <span class="badge bg-bark-50 text-bark-500" title="Computer voice (Sunbird AI)">🤖</span>
        @endif
        <span class="sr-only" role="status" x-text="state(url) === 'error' ? 'Could not play the recording.' : ''"></span>
    </span>
@endif
