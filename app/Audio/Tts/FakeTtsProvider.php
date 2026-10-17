<?php

namespace App\Audio\Tts;

/**
 * Free stand-in for trying the workflow: a short WAV of soft beeps, one per
 * syllable. It is clearly not Luganda speech and must never be approved for learners.
 */
class FakeTtsProvider implements TtsProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return config('luganda.tts.providers.fake.label', 'Test beep');
    }

    public function available(): bool
    {
        return true;
    }

    public function voices(): array
    {
        return ['beep-low', 'beep-high'];
    }

    public function defaultVoice(): string
    {
        return 'beep-low';
    }

    public function synthesize(string $text, string $voice): SynthesizedAudio
    {
        $rate = 16000;
        $syllables = max(1, preg_match_all('/[aeiou]+/iu', $text));
        $base = $voice === 'beep-high' ? 520.0 : 330.0;
        $samples = '';
        for ($s = 0; $s < $syllables; $s++) {
            $freq = $base * [1, 1.12, 1.25, 1.12, 0.9][$s % 5];
            for ($i = 0; $i < (int) ($rate * 0.14); $i++) {
                $env = min(1, $i / 200) * max(0, 1 - $i / ($rate * 0.14));
                $samples .= pack('v', (int) (sin(2 * M_PI * $freq * $i / $rate) * 9000 * $env) & 0xFFFF);
            }
            $samples .= str_repeat(pack('v', 0), (int) ($rate * 0.04));
        }
        $wav = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
            .'data'.pack('V', strlen($samples)).$samples;

        return new SynthesizedAudio($wav, 'audio/wav', 'wav', (int) round(strlen($samples) / 2 / $rate * 1000), ['fake' => true]);
    }
}
