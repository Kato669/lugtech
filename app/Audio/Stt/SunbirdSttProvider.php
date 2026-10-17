<?php

namespace App\Audio\Stt;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sunbird AI speech-to-text (https://docs.sunbird.ai/api-reference/speech/speech-to-text):
 * POST /tasks/audio/transcriptions, multipart: audio (file) + language=lug.
 * Uses the same SUNBIRD_API_TOKEN as text-to-speech. The recording is sent, the text
 * comes back, and the app keeps nothing but that text.
 */
class SunbirdSttProvider implements SttProvider
{
    private array $config;

    public function __construct()
    {
        $this->config = config('luganda.stt.providers.sunbird', []);
    }

    public function name(): string
    {
        return 'sunbird';
    }

    public function label(): string
    {
        return $this->config['label'] ?? 'Sunbird AI speech-to-text';
    }

    public function available(): bool
    {
        return filled($this->config['token'] ?? null);
    }

    public function transcribe(string $path, string $mime): string
    {
        if (! $this->available()) {
            throw new SttException('Sunbird is not set up: add SUNBIRD_API_TOKEN to .env.');
        }
        $ext = match (strtolower(trim(explode(';', $mime)[0]))) {
            'audio/webm', 'video/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a', 'video/mp4' => 'm4a',
            'audio/mpeg' => 'mp3',
            default => 'wav',
        };

        try {
            $res = Http::baseUrl(rtrim($this->config['url'] ?? 'https://api.sunbird.ai', '/'))
                ->withToken($this->config['token'])->acceptJson()->timeout($this->config['timeout'] ?? 60)
                ->attach('audio', fopen($path, 'r'), "attempt.{$ext}", ['Content-Type' => $mime])
                ->post('/tasks/audio/transcriptions', ['language' => 'lug']);
        } catch (ConnectionException $e) {
            throw new SttException('Could not reach Sunbird: '.$e->getMessage());
        }

        if (! $res->successful()) {
            $detail = $res->json('detail');
            $detail = is_array($detail) ? collect($detail)->pluck('msg')->implode('; ') : ($detail ?: $res->body());
            throw new SttException(match ($res->status()) {
                401, 403 => 'Sunbird refused the token (check SUNBIRD_API_TOKEN).',
                429 => 'Sunbird says too many requests: try again later.',
                default => "Sunbird error {$res->status()}: ".str($detail)->limit(200),
            });
        }

        return trim((string) $res->json('audio_transcription'));
    }
}
