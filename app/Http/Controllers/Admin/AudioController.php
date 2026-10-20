<?php

namespace App\Http\Controllers\Admin;

use App\Audio\Tts\TtsManager;
use App\Http\Controllers\Controller;
use App\Models\AudioClip;
use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\Word;
use App\Services\AudioGenerator;
use App\Services\AudioResolver;
use App\Services\ContentQueue;
use App\Support\CharacterVoices;
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
            'characters' => $this->characters(),
            'chooseUrl' => route('admin.audio.character-voice'),
        ]);
    }

    /**
     * Character voices: for Mukasa and Nakato, the chosen voice, the voice samples (the sample
     * phrase in each voice, in that character's slot) and every phrase they say.
     */
    private function characters(): array
    {
        $sample = Phrase::where('luganda', CharacterVoices::SAMPLE_TEXT)->orderBy('id')->first();
        $out = [];
        foreach (CharacterVoices::SLOTS as $character => $slot) {
            $ids = CharacterVoices::phraseIds($character);
            $phrases = Phrase::whereIn('id', $ids)->with('currentClips')->orderBy('id')->get();
            $out[$character] = [
                'name' => ucfirst($character),
                'slot' => $slot,
                'voice' => CharacterVoices::voiceFor($slot),
                'sample' => $sample ? ['type' => 'phrase', 'id' => $sample->id, 'luganda' => $sample->luganda] : null,
                'samples' => $sample ? $sample->currentClips()->where('slot', $slot)->where('source', 'generated')->get()
                    ->map(fn (AudioClip $c) => ['voice' => $c->voice, 'url' => $c->url(), 'status' => $c->status])->values() : [],
                'items' => $phrases->map(fn (Phrase $p) => [
                    'type' => 'phrase', 'id' => $p->id, 'code' => $p->code, 'luganda' => $p->luganda,
                    'done' => $p->currentClips->where('slot', $slot)->whereIn('status', ['approved', 'pending'])->isNotEmpty(),
                ])->values(),
            ];
        }

        return $out;
    }

    /** Pick the voice a character speaks with. The other voice samples are put aside (not deleted). */
    public function chooseCharacterVoice(Request $request, TtsManager $tts)
    {
        $data = $request->validate([
            'character' => ['required', 'in:'.implode(',', array_keys(CharacterVoices::SLOTS))],
            'voice' => ['required', 'string', 'max:60'],
        ]);
        $provider = $tts->provider();
        abort_unless($provider->available() && in_array($data['voice'], $provider->voices(), true), 422, 'Unknown voice.');
        $slot = CharacterVoices::SLOTS[$data['character']];
        CharacterVoices::choose($slot, $data['voice']);

        if ($sample = Phrase::where('luganda', CharacterVoices::SAMPLE_TEXT)->orderBy('id')->first()) {
            $sample->audioClips()->current()->where('slot', $slot)->where('source', 'generated')
                ->where('status', 'pending')->where('voice', '!=', $data['voice'])
                ->update(['is_current' => false, 'updated_at' => now()]);
            app(AudioResolver::class)->flush();
        }

        return response()->json(['ok' => true, 'voice' => $data['voice'], 'env' => filled(config('luganda.tts.character_voices.'.$slot))]);
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
            'slot' => ['nullable', 'in:normal,male,female'], // male = Mukasa's voice, female = Nakato's
        ]);
        $item = $data['type'] === 'word' ? Word::findOrFail($data['id']) : Phrase::findOrFail($data['id']);
        $slot = $data['slot'] ?? 'normal';
        $voice = $data['voice'] ?? ($slot !== 'normal' ? CharacterVoices::voiceFor($slot) : null);

        $result = $generator->generate($item, $data['provider'], $voice, $slot, $request->user(), $request->boolean('force'));

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
