<?php

namespace App\Support;

use App\Models\AudioClip;
use App\Models\Phrase;
use App\Models\Word;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Recordings that travel with the code, for hosts that start with an empty disk and
 * database (Render). On the computer where the recordings were made:
 *
 *   php artisan app:export-audio   → database/audio-pack/clips.json + files/<disk>/<path>
 *
 * The host runs `php artisan app:import-audio` at every start (docker/start.sh): missing
 * files are put back and missing recordings are added. Nothing is overwritten or removed.
 *
 * A recording is matched to its phrase or word by the English and the Luganda (compared
 * with LugandaText::key), never by database id, because ids differ between databases.
 */
class AudioPack
{
    public const STATUSES = ['approved', 'pending'];

    public static function defaultPath(): string
    {
        return database_path('audio-pack');
    }

    /** @return array{clips: int, missing_files: int, bytes: int} */
    public function export(string $dir): array
    {
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir.'/files');

        $rows = [];
        $missing = 0;
        $bytes = 0;
        $clips = AudioClip::current()->whereIn('status', self::STATUSES)
            ->whereIn('owner_type', ['phrase', 'word'])->with('owner')->orderBy('id')->get();

        foreach ($clips as $clip) {
            $owner = $clip->owner;
            $disk = Storage::disk($clip->disk);
            if (! $owner || ! $disk->exists($clip->path)) {
                $missing++;

                continue;
            }
            $target = $dir.'/files/'.$clip->disk.'/'.$clip->path;
            File::ensureDirectoryExists(dirname($target));
            File::put($target, $disk->get($clip->path));
            $bytes += File::size($target);

            $rows[] = [
                'owner' => array_filter([
                    'type' => $clip->owner_type,
                    'luganda' => $owner->luganda,
                    'english' => $owner->english,
                    'kind' => $clip->owner_type === 'word' ? $owner->kind : null,
                ], fn ($v) => $v !== null),
            ] + $clip->only([
                'slot', 'source', 'provider', 'voice', 'text_snapshot', 'text_key', 'disk', 'path',
                'mime', 'bytes', 'duration_ms', 'status', 'meta', 'generation_key',
            ]);
        }

        File::put($dir.'/clips.json', json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        return ['clips' => count($rows), 'missing_files' => $missing, 'bytes' => $bytes];
    }

    /** @return array{added: int, files: int, skipped: int, no_owner: int} */
    public function import(string $dir): array
    {
        $stats = ['added' => 0, 'files' => 0, 'skipped' => 0, 'no_owner' => 0];
        $manifest = $dir.'/clips.json';
        if (! File::exists($manifest)) {
            return $stats;
        }

        foreach (json_decode(File::get($manifest), true) ?: [] as $row) {
            $source = $dir.'/files/'.$row['disk'].'/'.$row['path'];
            if (! File::exists($source)) {
                $stats['skipped']++;

                continue;
            }

            // The file first: on a host without a lasting disk, it has to come back at every start.
            $disk = Storage::disk($row['disk']);
            if (! $disk->exists($row['path'])) {
                $disk->put($row['path'], File::get($source));
                $stats['files']++;
            }

            $owner = $this->findOwner($row['owner']);
            if (! $owner) {
                $stats['no_owner']++;

                continue;
            }
            $exists = AudioClip::where('owner_type', $row['owner']['type'])->where('owner_id', $owner->id)
                ->where('path', $row['path'])->exists();
            if ($exists) {
                continue;
            }

            AudioClip::create([
                'owner_type' => $row['owner']['type'],
                'owner_id' => $owner->id,
                'is_current' => true,
            ] + collect($row)->except('owner')->all());
            $stats['added']++;
        }

        return $stats;
    }

    private function findOwner(array $owner): Phrase|Word|null
    {
        $query = $owner['type'] === 'word' ? Word::query() : Phrase::query();
        if ($owner['type'] === 'word' && isset($owner['kind'])) {
            $query->where('kind', $owner['kind']);
        }
        $key = LugandaText::key($owner['luganda']);

        return $query->where('english', $owner['english'])->orderBy('id')->get()
            ->first(fn ($item) => LugandaText::key($item->luganda) === $key);
    }
}
