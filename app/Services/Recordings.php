<?php

namespace App\Services;

use App\Models\Phrase;
use App\Models\User;
use App\Models\Word;
use App\Support\LugandaText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Stores recordings against phrases and dictionary words. */
class Recordings
{
    /** Extensions we accept; browser recordings arrive as webm (Chrome/Android) or mp4/m4a (iPhone). */
    public const EXTENSIONS = ['mp3', 'm4a', 'mp4', 'aac', 'ogg', 'oga', 'opus', 'wav', 'webm', 'weba'];

    public const MAX_KB = 10240;

    /**
     * Read a file name such as "P0007-oli-otya-slow.m4a" or "w12 amazzi.mp3".
     *
     * Add "mukasa"/"male" or "nakato"/"female" after the code for a character voice.
     *
     * @return array{model: Phrase|Word, slow: bool, slot: string}|null
     */
    public function matchFilename(string $filename): ?array
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        if (! preg_match('/^\s*([PW])[-_ ]?0*(\d+)\b(.*)$/i', $base, $m)) {
            return null;
        }
        $model = strtoupper($m[1]) === 'P' ? Phrase::find((int) $m[2]) : Word::find((int) $m[2]);
        if (! $model) {
            return null;
        }

        $rest = $m[3];
        $slot = match (true) {
            $model instanceof Phrase && (bool) preg_match('/mukasa|\bmale\b|\bman\b|musajja/i', $rest) => 'male',
            $model instanceof Phrase && (bool) preg_match('/nakato|female|woman|mukazi/i', $rest) => 'female',
            (bool) preg_match('/slow|pole/i', $rest) => 'slow',
            default => 'normal',
        };

        return ['model' => $model, 'slow' => $slot === 'slow', 'slot' => $slot];
    }

    /**
     * Save a human recording for a phrase or word.
     *
     * It is stored as a new pending recording. A previously approved take keeps
     * playing for learners until a reviewer approves the new one; earlier takes
     * that were never approved are retired. The Luganda text keeps its approval:
     * the text and each recording are checked separately. No file is deleted.
     *
     * @param  bool|string  $slot  'normal' | 'slow' | 'male' | 'female' (true/false still mean slow/normal)
     * @return string the old-style column kept in step (audio_path, audio_slow_path…)
     */
    public function attach(Model $model, UploadedFile $file, bool|string $slot, ?User $by): string
    {
        $slot = is_bool($slot) ? ($slot ? 'slow' : 'normal') : $slot;
        $slot = $model instanceof Phrase && isset(Phrase::SLOTS[$slot]) ? $slot : 'normal';
        $column = $model instanceof Phrase ? Phrase::SLOTS[$slot] : 'audio_path';
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'webm');
        $ext = in_array($ext, self::EXTENSIONS, true) ? $ext : 'webm';
        $folder = $model instanceof Phrase ? 'audio/phrases' : 'audio/words';

        // Readable, unique file names: P0007-oli-otya-slow-<random>.webm
        $suffix = ['slow' => '-slow', 'male' => '-mukasa', 'female' => '-nakato'][$slot] ?? '';
        $name = pathinfo($model->recordingFilename(false, $ext), PATHINFO_FILENAME).$suffix
            .'-'.str()->random(6).'.'.$ext;
        $path = $file->storeAs($folder, $name, 'public');

        DB::transaction(function () use ($model, $file, $slot, $column, $path, $by) {
            // Unapproved earlier takes of this kind are replaced (kept on disk as history).
            $model->audioClips()->current()->where('slot', $slot)->where('source', 'human')
                ->where('status', '!=', 'approved')->update(['is_current' => false, 'updated_at' => now()]);

            $model->audioClips()->create([
                'slot' => $slot,
                'source' => 'human',
                'voice' => ['male' => 'mukasa', 'female' => 'nakato'][$slot] ?? null,
                'text_snapshot' => $model->luganda,
                'text_key' => LugandaText::key($model->luganda),
                'disk' => 'public',
                'path' => $path,
                'mime' => $file->getMimeType(),
                'bytes' => $file->getSize() ?: null,
                'status' => 'pending',
                'recorded_by' => $by?->id,
            ]);

            $model->forceFill([$column => $path, 'recorded_by' => $by?->id])->save();
        });
        $model->unsetRelation('currentClips');
        app(AudioResolver::class)->flush();

        return $column;
    }

    /** Stop using a recording slot (e.g. "Remove" in the admin). The files are kept as history. */
    public function retire(Model $model, string $slot): void
    {
        $model->audioClips()->current()->where('slot', $slot)->where('source', 'human')
            ->update(['is_current' => false, 'updated_at' => now()]);
        $column = $model instanceof Phrase ? (Phrase::SLOTS[$slot] ?? 'audio_path') : 'audio_path';
        $model->forceFill([$column => null])->save();
        $model->unsetRelation('currentClips');
        app(AudioResolver::class)->flush();
    }

    public static function fileRules(): array
    {
        return ['required', 'file', 'max:'.self::MAX_KB, function ($attr, $file, $fail) {
            $ext = strtolower($file->getClientOriginalExtension());
            $mime = (string) $file->getMimeType();
            $ok = in_array($ext, self::EXTENSIONS, true) || str_starts_with($mime, 'audio/') || $mime === 'video/webm';
            if (! $ok) {
                $fail('That does not look like an audio file.');
            }
        }];
    }
}
