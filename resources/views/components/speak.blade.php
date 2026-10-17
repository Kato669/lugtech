{{--
    Speaker button for a phrase or dictionary word: <x-speak :item="$phrase" />
    Plays the approved recording (see AudioResolver). Learners see nothing when
    there is no recording yet; reviewers see "no audio" or "pending" so they know
    what still needs doing.
--}}
@props(['item', 'slow' => true])
@php
    $info = app(\App\Services\AudioResolver::class)->info($item);
    $reviewer = auth()->user()?->isContributor();
@endphp
@if ($info['url'])
    <x-play-button :url="$info['url']" :slow="$slow ? $info['slow'] : null" :label="$item->luganda"
                   :pending="$reviewer && $info['status'] !== 'approved'" :generated="$info['source'] === 'generated'" />
@elseif ($voiceOn && $reviewer)
    <span class="badge bg-bark-50 align-middle text-bark-500" title="No recording yet: add one in the Studio">🔇 no audio</span>
@endif
