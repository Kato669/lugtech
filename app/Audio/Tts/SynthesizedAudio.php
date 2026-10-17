<?php

namespace App\Audio\Tts;

/** Audio returned by a provider, ready to be stored. */
final class SynthesizedAudio
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $mime,
        public readonly string $extension,
        public readonly ?int $durationMs = null,
        /** @var array<string, mixed> */
        public readonly array $meta = [],
    ) {}
}
