<?php

namespace App\Audio\Stt;

/** Finds the speech-to-text provider, if automatic checking is switched on. */
class SttManager
{
    /** @var array<string, class-string<SttProvider>> */
    public const PROVIDERS = [
        'fake' => FakeSttProvider::class,
        'sunbird' => SunbirdSttProvider::class,
    ];

    /** The provider to use, or null when checking is off (the default) or not set up. */
    public function provider(): ?SttProvider
    {
        if (! config('luganda.voice')) {
            return null; // no recordings at all while voices are off
        }
        $class = self::PROVIDERS[config('luganda.stt.default', 'off')] ?? null;
        $provider = $class ? app($class) : null;

        return $provider?->available() ? $provider : null;
    }

    public function enabled(): bool
    {
        return $this->provider() !== null;
    }
}
