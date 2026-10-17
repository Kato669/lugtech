<?php

namespace App\Http\Controllers\Contributor;

use App\Http\Controllers\Controller;
use App\Services\ContentQueue;
use App\Services\Recordings;
use Illuminate\Http\Request;

/** Printable recording script, CSV export, and bulk upload matched by code. */
class ScriptController extends Controller
{
    public function script(Request $request, ContentQueue $queue)
    {
        $missing = $request->boolean('missing');
        $items = $queue->all()
            ->when($missing, fn ($c) => $c->filter(fn ($i) => ! $i['model']->audio_path))
            ->map(fn ($i) => $queue->toArray($i));

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($items) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // so Excel shows Luganda letters (ŋ) correctly
                fputcsv($out, ['code', 'group', 'luganda', 'english', 'notes', 'file_name', 'slow_file_name', 'character_voice', 'character_file_name', 'has_audio', 'has_slow_audio', 'reviewed']);
                foreach ($items as $i) {
                    fputcsv($out, [
                        $i['code'], $i['group'], $i['luganda'], $i['english'], $i['notes'],
                        $i['code'].'-'.str($i['luganda'])->ascii()->slug()->limit(30, '').'.mp3',
                        $i['has_slow'] ? $i['code'].'-'.str($i['luganda'])->ascii()->slug()->limit(30, '').'-slow.mp3' : '',
                        ['male' => 'Mukasa (a man)', 'female' => 'Nakato (a woman)'][$i['voice']] ?? '',
                        $i['voice'] ? $i['code'].'-'.str($i['luganda'])->ascii()->slug()->limit(30, '').'-'.($i['voice'] === 'male' ? 'mukasa' : 'nakato').'.mp3' : '',
                        $i['audio'] ? 'yes' : 'no', $i['audio_slow'] ? 'yes' : ($i['has_slow'] ? 'no' : ''), $i['is_reviewed'] ? 'yes' : 'no',
                    ]);
                }
                fclose($out);
            }, 'luganda-recording-script.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('contributor.script', ['groups' => $items->groupBy('group'), 'missing' => $missing, 'count' => $items->count()]);
    }

    public function uploadForm()
    {
        return view('contributor.upload', ['extensions' => Recordings::EXTENSIONS, 'maxMb' => Recordings::MAX_KB / 1024]);
    }

    /** One file per request, so big batches don't hit PHP's upload limits. */
    public function upload(Request $request, Recordings $recordings)
    {
        $request->validate(['file' => Recordings::fileRules()]);
        $file = $request->file('file');
        $match = $recordings->matchFilename($file->getClientOriginalName());

        if (! $match) {
            return response()->json([
                'message' => 'No phrase or word matches this file name. Start the name with its code, e.g. P0007-oli-otya.mp3.',
            ], 422);
        }
        $column = $recordings->attach($match['model'], $file, $match['slot'], $request->user());

        return response()->json([
            'code' => $match['model']->code,
            'luganda' => $match['model']->luganda,
            'slow' => $column === 'audio_slow_path',
            'voice' => ['audio_male_path' => 'Mukasa', 'audio_female_path' => 'Nakato'][$column] ?? null,
        ]);
    }
}
