{{--
    Luganda shown as tappable words (Phase 11): <x-lug-text :text="$phrase->luganda" />
    Tapping a word opens the word sheet with its meaning, recording and dictionary link
    (App\Services\Glossary). Never use it where the meaning is the answer to a question.
--}}
@props(['text'])
<span {{ $attributes->merge(['class' => 'lug-words']) }}>{!! app(\App\Services\Glossary::class)->html((string) $text) !!}</span>
