<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Daily reminders by web push (Phase 12), with no extra packages and no paid service.
 *
 * Each reminder is an empty push signed with the app's own VAPID key (RFC 8292, ES256). Without
 * a payload nothing has to be encrypted; the service worker (public/sw.js) shows a fixed
 * "Time for a little Luganda" note. Keys: `php artisan app:push-keys`. Needs HTTPS.
 */
class WebPush
{
    public static function configured(): bool
    {
        return filled(config('luganda.push.public_key')) && filled(config('luganda.push.private_key'));
    }

    /**
     * A new key pair, as the two .env values.
     *
     * @return array{public: string, private: string}
     */
    public static function generateKeys(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (! $key) {
            throw new RuntimeException('OpenSSL could not make a P-256 key.');
        }
        openssl_pkey_export($key, $pem);
        $ec = openssl_pkey_get_details($key)['ec'];
        $point = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);

        return ['public' => self::b64url($point), 'private' => base64_encode($pem)];
    }

    /** Send one reminder. Returns the push service's HTTP status (201 = accepted). */
    public function send(PushSubscription $subscription, int $ttl = 43200): int
    {
        $response = Http::timeout(15)
            ->withHeaders([
                'TTL' => (string) $ttl,
                'Urgency' => 'normal',
                'Authorization' => $this->authorization($subscription->endpoint),
            ])
            ->withBody('', 'application/octet-stream')
            ->post($subscription->endpoint);

        return $response->status();
    }

    /** "vapid t=<JWT>, k=<public key>" for this push service. */
    public function authorization(string $endpoint, ?int $now = null): string
    {
        $parts = parse_url($endpoint);
        $audience = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $header = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64url(json_encode([
            'aud' => $audience,
            'exp' => ($now ?? time()) + 12 * 3600,
            'sub' => config('luganda.push.subject'),
        ], JSON_UNESCAPED_SLASHES));
        $input = $header.'.'.$claims;

        $private = openssl_pkey_get_private(base64_decode((string) config('luganda.push.private_key')));
        if (! $private || ! openssl_sign($input, $der, $private, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The push key (LUGANDA_PUSH_PRIVATE_KEY) could not be used.');
        }

        return 'vapid t='.$input.'.'.self::b64url(self::derToRaw($der)).', k='.config('luganda.push.public_key');
    }

    /** OpenSSL gives an ASN.1 DER signature; JWT wants r and s as 32 bytes each. */
    public static function derToRaw(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0); // SEQUENCE header
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$offset + 1]);
            $int = substr($der, $offset + 2, $len);
            $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $len;
        }

        return $out;
    }

    public static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
