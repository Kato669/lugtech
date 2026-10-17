<?php

namespace App\Audio\Tts;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Sunbird AI text-to-speech (https://docs.sunbird.ai), Luganda voices.
 *
 * Asks for a signed audio URL (the documented "url" mode), then downloads the
 * file straight away: Sunbird's links expire after about 30 minutes, so the app
 * always keeps its own copy.
 */
class SunbirdTtsProvider implements TtsProvider
{
    private array $config;

    public function __construct()
    {
        $this->config = config('luganda.tts.providers.sunbird', []);
    }

    public function name(): string
    {
        return 'sunbird';
    }

    public function label(): string
    {
        return $this->config['label'] ?? 'Sunbird AI';
    }

    public function available(): bool
    {
        return filled($this->config['token'] ?? null);
    }

    public function voices(): array
    {
        $fallback = $this->config['voices'] ?? [];
        if (! $this->available()) {
            return $fallback;
        }

        // Ask Sunbird once a day; fall back to the known list if that fails.
        return Cache::remember('tts:sunbird:voices:lug', now()->addDay(), function () use ($fallback) {
            try {
                $res = $this->http(20)->get('/tasks/voice/speakers', ['language' => 'lug']);

                return $res->successful() && $res->json('speakers') ? array_values($res->json('speakers')) : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        });
    }

    public function defaultVoice(): string
    {
        return $this->config['voice'] ?? ($this->voices()[0] ?? 'salt_lug_0001');
    }

    public function synthesize(string $text, string $voice): SynthesizedAudio
    {
        if (! $this->available()) {
            throw new TtsException('Sunbird is not set up: add SUNBIRD_API_TOKEN to .env.');
        }

        try {
            $res = $this->http($this->config['timeout'] ?? 90)->post('/tasks/audio/speech', array_filter([
                'text' => $text,
                'voice' => $voice,
                'language' => 'lug',
                'response_mode' => 'url',
                'seed' => $this->config['seed'] ?? null,
            ], fn ($v) => $v !== null));
        } catch (ConnectionException $e) {
            throw new TtsException('Could not reach Sunbird: '.$e->getMessage());
        }

        if (! $res->successful()) {
            $detail = $res->json('detail');
            $detail = is_array($detail) ? collect($detail)->pluck('msg')->implode('; ') : ($detail ?: $res->body());
            throw new TtsException(match ($res->status()) {
                401, 403 => 'Sunbird refused the token (check SUNBIRD_API_TOKEN).',
                429 => 'Sunbird says too many requests: try again later.',
                default => "Sunbird error {$res->status()}: ".str($detail)->limit(200),
            });
        }

        $url = $res->json('audio_url');
        if (! $url) {
            throw new TtsException('Sunbird did not return an audio link.');
        }

        try {
            $file = Http::timeout(60)->get($url);
        } catch (ConnectionException $e) {
            throw new TtsException('Could not download the audio from Sunbird: '.$e->getMessage());
        }
        if (! $file->successful() || $file->body() === '') {
            throw new TtsException("Could not download the audio from Sunbird ({$file->status()}).");
        }

        [$mime, $ext] = $this->format($file->header('Content-Type'), $url);
        $seconds = $res->json('duration_seconds') ?? $res->json('usage.duration_seconds');

        return new SynthesizedAudio($file->body(), $mime, $ext, $seconds ? (int) round($seconds * 1000) : null, array_filter([
            'request_id' => $res->json('request_id'),
            'sample_rate' => $res->json('sample_rate'),
            'model' => $res->json('model'),
            'input_characters' => $res->json('usage.input_characters'),
        ], fn ($v) => $v !== null));
    }

    private function http(int $timeout)
    {
        return Http::baseUrl(rtrim($this->config['url'] ?? 'https://api.sunbird.ai', '/'))
            ->withToken($this->config['token'])->acceptJson()->timeout($timeout);
    }

    /** @return array{0: string, 1: string} mime type and file extension */
    private function format(?string $contentType, string $url): array
    {
        $known = ['wav' => 'audio/wav', 'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'opus' => 'audio/ogg', 'webm' => 'audio/webm', 'm4a' => 'audio/mp4', 'flac' => 'audio/flac'];
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (isset($known[$ext])) {
            return [$known[$ext], $ext];
        }
        $type = strtolower(trim(explode(';', (string) $contentType)[0]));
        $byType = ['audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/wave' => 'wav', 'audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/webm' => 'webm', 'audio/flac' => 'flac', 'audio/mp4' => 'm4a'];

        return isset($byType[$type]) ? [$type, $byType[$type]] : ['audio/wav', 'wav'];
    }
}
