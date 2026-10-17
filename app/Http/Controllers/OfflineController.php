<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use App\Services\LessonPlan;
use Illuminate\Http\Request;

/** Working offline (Phase 9): the offline page, a fresh token for sending saved progress, and "Save for offline". */
class OfflineController extends Controller
{
    /** Shown by the service worker when a page isn't saved on the phone. No account data on it. */
    public function page()
    {
        return response()->view('offline')->header('Cache-Control', 'no-cache');
    }

    /** A fresh CSRF token for sending progress that was saved offline (pages saved earlier carry an old one). */
    public function token(Request $request)
    {
        return response()->json(['token' => csrf_token(), 'user_id' => $request->user()->id])->header('Cache-Control', 'no-store');
    }

    /** Everything a stage needs offline: its lesson pages and every recording they play. */
    public function stage(Request $request, Stage $stage)
    {
        $urls = collect([route('course'), route('review'), route('phrasebook')]);
        $titles = [];
        $plan = new LessonPlan(1);
        foreach ($stage->publishedLessons as $lesson) {
            $urls->push(route('lessons.show', $lesson));
            $titles['/learn/'.$lesson->slug] = $lesson->title; // so the offline page can list it by name
            $steps = $plan->build($lesson);
            array_walk_recursive($steps, function ($value, $key) use ($urls) {
                if (in_array($key, ['audio', 'audio_slow'], true) && is_string($value) && $value !== '') {
                    $urls->push($value);
                }
            });
        }
        $urls = $urls->map(fn ($u) => parse_url($u, PHP_URL_PATH).(parse_url($u, PHP_URL_QUERY) ? '?'.parse_url($u, PHP_URL_QUERY) : ''))->unique()->values();

        return response()->json([
            'stage' => $stage->title_lg,
            'lessons' => $stage->publishedLessons->count(),
            'urls' => $urls,
            'titles' => $titles,
        ]);
    }
}
