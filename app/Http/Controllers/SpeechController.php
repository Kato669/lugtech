<?php

namespace App\Http\Controllers;

use App\Audio\Stt\SpeechScore;
use App\Audio\Stt\SttException;
use App\Audio\Stt\SttManager;
use App\Models\Phrase;
use App\Models\SpeechAttempt;
use App\Services\DailyHabit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Speaking practice: the learner says a phrase, compares it with the recording and
 * says whether it sounded the same. Optionally, an automatic check listens too.
 * The learner's audio is never stored.
 */
class SpeechController extends Controller
{
    /** "Sounded the same" / "Not yet". Creates the attempt, or rates one that was just checked. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'phrase_id' => ['required', 'integer', 'exists:phrases,id'],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'source' => ['nullable', Rule::in(SpeechAttempt::SOURCES)],
            'self_rating' => ['required', Rule::in(SpeechAttempt::RATINGS)],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'attempt_id' => ['nullable', 'integer'],
            'active_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        $attempt = isset($data['attempt_id'])
            ? SpeechAttempt::where('id', $data['attempt_id'])->where('user_id', $request->user()->id)->where('phrase_id', $data['phrase_id'])->firstOrFail()
            : new SpeechAttempt(['user_id' => $request->user()->id, 'phrase_id' => $data['phrase_id']]);
        $attempt->fill([
            'lesson_id' => $data['lesson_id'] ?? $attempt->lesson_id,
            'source' => $data['source'] ?? $attempt->source ?? 'learn',
            'self_rating' => $data['self_rating'],
            'duration_ms' => $data['duration_ms'] ?? $attempt->duration_ms,
        ])->save();

        // Daily tasks (Phase 12): a new attempt counts once; re-rating the same attempt doesn't.
        $user = $request->user();
        $habit = app(DailyHabit::class)->record($user, $attempt->wasRecentlyCreated ? 'speak' : null, (int) ($data['active_seconds'] ?? 0));
        $user->save();

        return response()->json(['attempt_id' => $attempt->id, 'cowries' => $user->cowries, 'habit' => $habit]);
    }

    /** The automatic check (only when LUGANDA_STT is set up): what was heard, and how close. */
    public function check(Request $request, SttManager $stt)
    {
        $provider = $stt->provider();
        abort_unless($provider, 404, 'Automatic checking is switched off.');

        $data = $request->validate([
            'phrase_id' => ['required', 'integer', 'exists:phrases,id'],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'source' => ['nullable', Rule::in(SpeechAttempt::SOURCES)],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'audio' => ['required', 'file', 'max:'.config('luganda.stt.max_kb', 2048)],
        ]);
        $user = $request->user();

        $resting = ['ok' => false, 'message' => 'Automatic checking is resting for now. You can still compare and check yourself.'];
        if (SpeechAttempt::whereNotNull('provider')->where('created_at', '>=', today())->count() >= config('luganda.stt.daily_limit', 100)) {
            return response()->json($resting + ['reason' => 'daily_limit'], 429);
        }
        if (SpeechAttempt::where('user_id', $user->id)->whereNotNull('provider')->where('created_at', '>=', now()->subHour())->count() >= config('luganda.stt.per_learner_hourly', 30)) {
            return response()->json($resting + ['reason' => 'hourly_limit'], 429);
        }

        $phrase = Phrase::findOrFail($data['phrase_id']);
        $file = $request->file('audio');
        $attempt = new SpeechAttempt([
            'user_id' => $user->id, 'phrase_id' => $phrase->id, 'lesson_id' => $data['lesson_id'] ?? null,
            'source' => $data['source'] ?? 'learn', 'provider' => $provider->name(), 'duration_ms' => $data['duration_ms'] ?? null,
        ]);

        try {
            $heard = $provider->transcribe($file->getRealPath(), $file->getClientMimeType() ?: 'audio/webm');
        } catch (SttException $e) {
            $attempt->fill(['error' => str($e->getMessage())->limit(250)->toString()])->save();

            return response()->json(['ok' => false, 'attempt_id' => $attempt->id, 'message' => "Couldn't check this one. Compare it yourself and choose below."]);
        } finally {
            @unlink($file->getRealPath()); // the learner's voice is not kept
        }

        $score = SpeechScore::score($phrase->luganda, $heard);
        $attempt->fill(['transcript' => str($heard)->limit(490)->toString(), 'score' => $score])->save();

        return response()->json([
            'ok' => true,
            'attempt_id' => $attempt->id,
            'heard' => $heard,
            'score' => $score,
            'verdict' => $heard === '' ? 'silent' : SpeechScore::verdict($score),
        ]);
    }
}
