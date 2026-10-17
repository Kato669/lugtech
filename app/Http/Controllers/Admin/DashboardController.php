<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Phrase;
use App\Models\Stage;
use App\Models\User;
use App\Models\Word;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'counts' => [
                'Stages' => Stage::count(),
                'Lessons' => Lesson::count(),
                'Published lessons' => Lesson::where('is_published', true)->count(),
                'Phrases' => Phrase::count(),
                'Phrases needing review' => Phrase::where('is_reviewed', false)->count(),
                'Flagged by reviewers' => Phrase::flagged()->count() + Word::flagged()->count(),
                'Phrases without audio' => Phrase::whereNull('audio_path')->count(),
                'Dictionary entries' => Word::count(),
                'Registered learners' => User::where('is_admin', false)->where('is_guest', false)->count(),
                'Guest learners' => User::where('is_guest', true)->count(),
                'Reviewers' => User::where('is_reviewer', true)->count(),
                'Lessons completed' => LessonCompletion::sum('times_completed'),
            ],
        ]);
    }
}
