<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\TeacherRecording;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * What Mukasa says in English, so it can be spoken: a real recording when an admin has made one
 * (Admin → 🎙️ Mukasa's lines), otherwise the phone's built-in voice (resources/js/teacher-voice.js).
 *
 * The lines are the speech-bubble texts of the lessons: every step's own line (say / prompt) and its
 * feedback, plus the player's everyday lines (DEFAULTS, kept in step with resources/js/lesson-player.js).
 */
class TeacherLines
{
    /** Who speaks out loud for now. */
    public const CHARACTERS = ['mukasa'];

    /** The lesson player's own lines (TEACHER_SAYS, the praises and ENCOURAGE in lesson-player.js). */
    public const DEFAULTS = [
        'Listen, then say it with me.',
        'Your turn to speak! Listen, then say it.',
        'What does this mean?',
        'How do you say this in Luganda?',
        'Listen carefully. What did I say?',
        'Put the words in the right order.',
        'Match each word with its meaning.',
        'What comes next?',
        'Find the right picture.',
        'Look at how the words change.',
        'Put each word in its basket.',
        'Kulika! Well done!',
        'Kirungi! Good!',
        'Weewaawo! Exactly!',
        'Ggwe! Well done!',
        'Bulungi nnyo! Very good!',
        "Not quite. That's how we learn!",
        'Almost! Look at the answer below.',
        "Don't worry, it will come back later.",
    ];

    public const FILE_RULES = ['required', 'file', 'max:5120', 'mimes:mp3,m4a,mp4,ogg,oga,wav,webm,weba,aac'];

    /** The same line always gets the same key, however its spaces were typed. */
    public static function key(string $text): string
    {
        return sha1(self::tidy($text));
    }

    public static function tidy(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Every line Mukasa may say: the everyday lines, then each published lesson's lines in course order.
     * A step that belongs to Nakato (or Ngoma) by name is left out; other lessons' lines are included,
     * because a learner can choose Mukasa as their teacher (Phase 13).
     *
     * @return array<string, array{text:string, where:string[], his:bool}> keyed by TeacherLines::key()
     */
    public static function forMukasa(): array
    {
        $lines = [];
        $add = function (string $text, ?string $where, bool $his) use (&$lines) {
            $text = self::tidy($text);
            if ($text === '') {
                return;
            }
            $k = self::key($text);
            $lines[$k] ??= ['text' => $text, 'where' => [], 'his' => false];
            if ($where && ! in_array($where, $lines[$k]['where'], true)) {
                $lines[$k]['where'][] = $where;
            }
            $lines[$k]['his'] = $lines[$k]['his'] || $his;
        };
        foreach (self::DEFAULTS as $text) {
            $add($text, 'Every lesson', true);
        }

        $lessons = Lesson::where('is_published', true)->with(['stage', 'steps' => fn ($q) => $q->orderBy('position')])
            ->get()->sortBy(fn ($l) => [$l->stage->position, $l->position]);
        foreach ($lessons as $lesson) {
            $teacher = CharacterVoices::teacherFor($lesson);
            foreach ($lesson->steps as $step) {
                $p = $step->payload;
                $who = $p['character'] ?? $teacher;
                if (in_array($step->type, ['dialogue', 'converse'], true) || ($who !== 'mukasa' && isset($p['character']))) {
                    continue; // conversations, and lines that belong to another guide by name
                }
                $his = $who === 'mukasa';
                foreach ([$p['say'] ?? ($p['prompt'] ?? null), $p['feedback']['correct'] ?? null, $p['feedback']['wrong'] ?? null] as $text) {
                    if (is_string($text)) {
                        $add($text, $lesson->title, $his);
                    }
                }
            }
        }

        return $lines;
    }

    /** @return array<string, string> tidied line => recording address, for the lesson player */
    public static function recordings(string $character = 'mukasa'): array
    {
        return TeacherRecording::where('character', $character)->get()
            ->mapWithKeys(fn ($r) => [self::tidy($r->text) => $r->url()])->all();
    }

    public static function store(string $character, string $text, UploadedFile $file, ?int $userId): TeacherRecording
    {
        $text = self::tidy($text);
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'webm');
        if ($ext === 'weba') {
            $ext = 'webm';
        }
        $path = $file->storeAs('audio/teacher', $character.'-'.substr(self::key($text), 0, 10).'-'.bin2hex(random_bytes(5)).'.'.$ext, 'public');

        $old = TeacherRecording::where('character', $character)->where('text_key', self::key($text))->first();
        if ($old) {
            Storage::disk('public')->delete($old->path); // a new take replaces the old one (each gets a new name: cached for a year)
            $old->update(['text' => $text, 'path' => $path, 'recorded_by' => $userId]);

            return $old;
        }

        return TeacherRecording::create(['character' => $character, 'text' => $text, 'text_key' => self::key($text), 'path' => $path, 'recorded_by' => $userId]);
    }
}
