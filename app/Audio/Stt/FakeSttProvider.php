<?php

namespace App\Audio\Stt;

/**
 * For tests and trying the workflow without an account: "hears" the text inside
 * a small text file (upload a .txt saying "Oli otya" and it hears "Oli otya").
 * Real audio comes back as nothing heard. Never real speech recognition.
 */
class FakeSttProvider implements SttProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return config('luganda.stt.providers.fake.label', 'Test listener');
    }

    public function available(): bool
    {
        return true;
    }

    public function transcribe(string $path, string $mime): string
    {
        $bytes = (string) @file_get_contents($path, length: 2000);
        if (str_contains($bytes, 'FAIL')) {
            throw new SttException('The test listener was asked to fail.');
        }

        return mb_check_encoding($bytes, 'UTF-8') && ! preg_match('/[\x00-\x08\x0E-\x1F]/', $bytes) ? trim($bytes) : '';
    }
}
