<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Copies recordings saved in the old audio_* columns into audio_clips.
 *
 * - Before audio_clips existed, approving a phrase approved all its recordings,
 *   so recordings of an approved phrase arrive approved; the rest arrive pending.
 * - Recordings on "Gyendi" arrive flagged: a test recording is known to have
 *   been saved there by mistake. Nothing is moved or replaced automatically.
 * - Safe to run again: a file already imported is skipped.
 */
class LegacyAudioImport
{
    public const GYENDI_NOTE = 'Please listen: an earlier note says a test recording was saved on "Gyendi" by mistake (possibly Mukasa saying "Oli otya?"). Approve only takes that really say "Gyendi"; reject the others.';

    /** @return int number of recordings imported */
    public static function run(): int
    {
        $count = 0;
        $tables = [
            'phrase' => ['phrases', ['normal' => 'audio_path', 'slow' => 'audio_slow_path', 'male' => 'audio_male_path', 'female' => 'audio_female_path']],
            'word' => ['words', ['normal' => 'audio_path']],
        ];

        foreach ($tables as $type => [$table, $slots]) {
            $columns = array_values(array_filter($slots, fn ($c) => Schema::hasColumn($table, $c)));
            if (! $columns) {
                continue;
            }
            $rows = DB::table($table)->where(fn ($q) => collect($columns)->each(fn ($c) => $q->orWhereNotNull($c)))->get();

            foreach ($rows as $row) {
                foreach ($slots as $slot => $column) {
                    $path = $row->{$column} ?? null;
                    if (! $path || DB::table('audio_clips')->where('owner_type', $type)->where('owner_id', $row->id)->where('path', $path)->exists()) {
                        continue;
                    }
                    $suspect = $type === 'phrase' && LugandaText::key($row->luganda) === 'gyendi';
                    $approved = (bool) $row->is_reviewed && ! $suspect;

                    DB::table('audio_clips')->insert([
                        'owner_type' => $type,
                        'owner_id' => $row->id,
                        'slot' => $slot,
                        'source' => 'human',
                        'voice' => ['male' => 'mukasa', 'female' => 'nakato'][$slot] ?? null,
                        'text_snapshot' => $row->luganda,
                        'text_key' => LugandaText::key($row->luganda),
                        'disk' => 'public',
                        'path' => $path,
                        'mime' => self::mime($path),
                        'bytes' => self::size($path),
                        'status' => $suspect ? 'flagged' : ($approved ? 'approved' : 'pending'),
                        'is_current' => true,
                        'recorded_by' => $row->recorded_by ?? null,
                        'reviewed_by' => $approved ? ($row->reviewed_by ?? null) : null,
                        'reviewed_at' => $approved ? ($row->reviewed_at ?? null) : null,
                        'review_note' => $suspect ? self::GYENDI_NOTE : null,
                        'meta' => json_encode(['imported_from' => $column]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }

    private static function mime(string $path): ?string
    {
        return [
            'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4', 'aac' => 'audio/aac', 'ogg' => 'audio/ogg',
            'oga' => 'audio/ogg', 'opus' => 'audio/ogg', 'wav' => 'audio/wav', 'webm' => 'audio/webm', 'weba' => 'audio/webm',
        ][strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
    }

    private static function size(string $path): ?int
    {
        try {
            return Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
