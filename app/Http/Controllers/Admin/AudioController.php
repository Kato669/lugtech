<?php

namespace App\Http\Controllers\Admin;

use App\Audio\Tts\TtsManager;
use App\Http\Controllers\Controller;
use App\Models\AudioClip;
use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\Word;
use App\Services\AudioGenerator;
use App\Services\ContentQueue;
use Illuminate\Http\Request;

/** Admin → Generate audio: pick phrases or words, pick a voice, generate one by one. */
class AudioController extends Controller
{
    public function index(Request $request, ContentQueue $queue, TtsManager $tts, AudioGenerator $generator)
    {
        $lessons = Lesson::with('stage')->get()->sortBy(fn ($l) => [$l->stage->position, $l->position])
            ->map(fn ($l) => ['group' => "Stage {$l->stage->position} · {$l->title}", 'slug' => $l->slug])->values();

        $items = $queue->all()->map(function ($i) {
            $m = $i['model'];
            $clips = $m->currentClips;

            return [
                'type' => $i['type'], 'id' => $m->id, 'code' => $m->code, 'group' => $i['group'],
                'luganda' => $m->luganda, 'english' => $m->english, 'text_ok' => $m->is_reviewed,
                'human' => $this->state($clips->where('slot', 'normal')->where('source', 'human')),
                'generated' => $clips->where('slot', 'normal')->where('source', 'generated')
                    ->map(fn (AudioClip $c) => ['voice' => $c->voice, 'provider' => $c->provider, 'status' => $c->status, 'url' => $c->url()])->values(),
            ];
        })->values();

        $providers = collect($tts->all())->map(fn ($p) => [
            'name' => $p->name(), 'label' => $p->label(), 'available' => $p->available(),
            'voices' => $p->available() ? $p->voices() : [], 'default_voice' => $p->available() ? $p->defaultVoice() : null,
        ])->values();

        return view('admin.audio.index', [
            'items' => $items,
            'lessons' => $lessons,
            'providers' => $providers,
            'defaultProvider' => $tts->defaultName(),
            'remaining' => $generator->remainingToday(),
            'limit' => (int) config('luganda.tts.daily_limit'),
        ]);
    }

    /** One item per request, so the page can show progress and a slow provider can't time out a big batch. */
    public function generate(Request $request, AudioGenerator $generator, TtsManager $tts)
    {
        $data = $request->validate([
            'type' => ['required', 'in:phrase,word'],
            'id' => ['required', 'integer'],
            'provider' => ['required', 'in:'.implode(',', array_keys(TtsManager::PROVIDERS))],
            'voice' => ['nullable', 'string', 'max:60'],
            'force' => ['boolean'],
        ]);
        $item = $data['type'] === 'word' ? Word::findOrFail($data['id']) : Phrase::findOrFail($data['id']);

        $result = $generator->generate($item, $data['provider'], $data['voice'] ?? null, 'normal', $request->user(), $request->boolean('force'));

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'url' => $result['clip']?->url(),
            'voice' => $result['clip']?->voice,
            'remaining' => $generator->remainingToday(),
        ], $result['status'] === 'failed' ? 422 : 200);
    }

    private function state($clips): ?string
    {
        foreach (['approved', 'pending', 'flagged'] as $s) {
            if ($clips->firstWhere('status', $s)) {
                return $s;
            }
        }

        return null;
    }
}
