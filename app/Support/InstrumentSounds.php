<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Real recordings of the five instruments on the home page ("Sounds you'll hear"). An admin
 * uploads them on Admin → Instrument sounds; the home page loops a recording until the same
 * instrument (or another) is tapped. Without a recording, the built-in rhythm plays instead.
 *
 * Files: storage/app/public/audio/instruments/{key}-{random}.{ext} (a new name every time,
 * because recordings are cached for a year). Which file belongs to which instrument:
 * storage/app/instrument-sounds.json.
 */
class InstrumentSounds
{
    /** key => [name, what it is] (the order on the home page) */
    public const INSTRUMENTS = [
        'engalabi' => ['Engalabi', 'long hand drum'],
        'embuutu' => ['Embuutu', 'big bass drum'],
        'endongo' => ['Endongo', 'bowl lyre'],
        'amadinda' => ['Amadinda', 'log xylophone'],
        'ensaasi' => ['Ensaasi', 'gourd shakers'],
    ];

    public const FILE_RULES = ['required', 'file', 'max:10240', 'mimes:mp3,m4a,ogg,oga,wav,webm,aac'];

    private const INDEX = 'instrument-sounds.json';

    /** @return array<string, string> key => stored path (only instruments that have one) */
    public static function paths(): array
    {
        $json = Storage::disk('local')->exists(self::INDEX) ? json_decode((string) Storage::disk('local')->get(self::INDEX), true) : [];
        $paths = array_intersect_key(is_array($json) ? $json : [], self::INSTRUMENTS);

        return array_filter($paths, fn ($p) => is_string($p) && Storage::disk('public')->exists($p));
    }

    /** @return array<string, string|null> key => web address of the recording, or null */
    public static function urls(): array
    {
        $paths = self::paths();

        return array_map(fn ($key) => isset($paths[$key]) ? '/storage/'.$paths[$key] : null, array_combine(array_keys(self::INSTRUMENTS), array_keys(self::INSTRUMENTS)));
    }

    public static function store(string $key, UploadedFile $file): string
    {
        abort_unless(isset(self::INSTRUMENTS[$key]), 404);
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'mp3');
        $path = $file->storeAs('audio/instruments', $key.'-'.bin2hex(random_bytes(6)).'.'.$ext, 'public');
        self::remove($key);
        self::save([$key => $path] + self::paths());

        return $path;
    }

    public static function remove(string $key): void
    {
        $paths = self::paths();
        if (isset($paths[$key])) {
            Storage::disk('public')->delete($paths[$key]);
            unset($paths[$key]);
            self::save($paths);
        }
    }

    private static function save(array $paths): void
    {
        Storage::disk('local')->put(self::INDEX, json_encode($paths, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
