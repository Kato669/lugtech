<?php

namespace App\Audio\Tts;

/**
 * A text-to-speech service. Add a new one by implementing this and listing it
 * in TtsManager and config/luganda.php ('tts.providers').
 */
interface TtsProvider
{
    /** Short id stored with each recording, e.g. "sunbird". */
    public function name(): string;

    /** Human label for the admin page. */
    public function label(): string;

    /** Configured and ready to use (e.g. has an API token)? */
    public function available(): bool;

    /** Voices for Luganda; the first is the default. @return string[] */
    public function voices(): array;

    public function defaultVoice(): string;

    /** @throws TtsException */
    public function synthesize(string $text, string $voice): SynthesizedAudio;
}
