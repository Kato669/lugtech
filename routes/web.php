<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Contributor;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\OfflineController;
use App\Http\Controllers\PhrasebookController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SpeechController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureContributor;
use App\Http\Middleware\EnsureLearner;
use App\Http\Middleware\OnceOnly;
use App\Http\Middleware\RedirectIfRegistered;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/dictionary', DictionaryController::class)->name('dictionary');

// Working offline (Phase 9): the page the service worker shows when a page isn't saved on the phone,
// and a fresh token for sending progress that was saved offline.
Route::get('/offline', [OfflineController::class, 'page'])->name('offline');
Route::get('/sync/token', [OfflineController::class, 'token'])->middleware('auth')->name('sync.token');

// Open to visitors and to guest learners (who register to keep their progress).
Route::middleware(RedirectIfRegistered::class)->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:20,1');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// No sign-up needed: visitors get a guest account automatically.
Route::middleware(EnsureLearner::class)->group(function () {
    Route::get('/learn', CourseController::class)->name('course');
    Route::get('/learn/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
    Route::post('/learn/{lesson}/complete', [LessonController::class, 'complete'])->middleware(OnceOnly::class)->name('lessons.complete');
    Route::get('/offline/stage/{stage}', [OfflineController::class, 'stage'])->name('offline.stage');

    Route::get('/review', [ReviewController::class, 'index'])->name('review');
    Route::post('/review/{item}', [ReviewController::class, 'grade'])->middleware(OnceOnly::class)->name('review.grade');

    Route::get('/phrasebook', PhrasebookController::class)->name('phrasebook');

    Route::get('/games', [GameController::class, 'index'])->name('games.index');
    Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::post('/games/{game}/complete', [GameController::class, 'complete'])->middleware(OnceOnly::class)->name('games.complete');

    // Speaking practice: the learner's verdict, and the optional automatic check. Audio is never stored.
    Route::post('/speak/attempts', [SpeechController::class, 'store'])->middleware(['throttle:60,1', OnceOnly::class])->name('speak.attempts');
    Route::post('/speak/check', [SpeechController::class, 'check'])->middleware('throttle:20,1')->name('speak.check');
});

// Native speakers: record audio and check the Luganda.
Route::middleware(['auth', EnsureContributor::class])->group(function () {
    Route::get('/studio', [Contributor\StudioController::class, 'index'])->name('studio');
    Route::get('/studio/script', [Contributor\ScriptController::class, 'script'])->name('studio.script');
    Route::get('/studio/upload', [Contributor\ScriptController::class, 'uploadForm'])->name('studio.upload');
    Route::post('/studio/upload', [Contributor\ScriptController::class, 'upload']);
    Route::post('/studio/{type}/{id}', [Contributor\StudioController::class, 'store'])
        ->whereIn('type', ['phrase', 'word'])->whereNumber('id')->name('studio.store');

    Route::get('/check', [Contributor\CheckController::class, 'index'])->name('check');
    Route::post('/check/audio/{clip}', [Contributor\AudioReviewController::class, 'update'])
        ->whereNumber('clip')->name('check.audio');
    Route::delete('/check/audio/{clip}', [Contributor\AudioReviewController::class, 'destroy'])
        ->whereNumber('clip')->name('check.audio.destroy');
    Route::post('/check/{type}/{id}', [Contributor\CheckController::class, 'update'])
        ->whereIn('type', ['phrase', 'word'])->whereNumber('id')->name('check.update');
});

Route::middleware(['auth', EnsureAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::resource('stages', Admin\StageController::class)->except('show');
    Route::resource('lessons', Admin\LessonController::class)->except('show')
        ->scoped(['lesson' => 'id']);
    Route::resource('phrases', Admin\PhraseController::class)->except('show');
    Route::resource('words', Admin\WordController::class)->except('show');
    Route::get('audio', [Admin\AudioController::class, 'index'])->name('audio.index');
    Route::post('audio/generate', [Admin\AudioController::class, 'generate'])->name('audio.generate');
    Route::get('people', [Admin\PeopleController::class, 'index'])->name('people.index');
    Route::patch('people/{user}', [Admin\PeopleController::class, 'update'])->name('people.update');
});
