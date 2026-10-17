<?php

namespace App\Audio\Tts;

use InvalidArgumentException;

/** Finds text-to-speech providers by name. */
class TtsManager
{
    /** @var array<string, class-string<TtsProvider>> */
    public const PROVIDERS = [
        'fake' => FakeTtsProvider::class,
        'sunbird' => SunbirdTtsProvider::class,
    ];

    /** @var array<string, TtsProvider> */
    private array $made = [];

    public function provider(?string $name = null): TtsProvider
    {
        $name ??= $this->defaultName();
        $class = self::PROVIDERS[$name] ?? throw new InvalidArgumentException("Unknown audio provider \"{$name}\".");

        return $this->made[$name] ??= app($class);
    }

    public function defaultName(): string
    {
        return config('luganda.tts.default', 'fake');
    }

    /** @return array<string, TtsProvider> */
    public function all(): array
    {
        return collect(array_keys(self::PROVIDERS))->mapWithKeys(fn ($n) => [$n => $this->provider($n)])->all();
    }
}
