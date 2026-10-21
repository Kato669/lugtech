<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\Contributor;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CultureController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\OfflineController;
use App\Http\Controllers\PhrasebookController;
use App\Http\Controllers\PhrasePageController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SpeechController;
use App\Http\Controllers\WardrobeController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureContributor;
use App\Http\Middleware\EnsureLearner;
use App\Http\Middleware\OnceOnly;
use App\Http\Middleware\RedirectIfRegistered;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/dictionary', DictionaryController::class)->name('dictionary');

// Phase 11: public word and phrase pages, found through search engines. Outside EnsureLearner
// on purpose: visitors and crawlers must not get guest accounts or be redirected.
Route::get('/dictionary/{word:slug}', [DictionaryController::class, 'show'])->name('dictionary.word');
Route::get('/say', [PhrasePageController::class, 'index'])->name('say.index');
Route::get('/say/{phrase:slug}', [PhrasePageController::class, 'show'])->name('say.show');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Phase 15: culture (public, like the dictionary): proverbs, clans and names, stories, a four-week guide.
Route::get('/culture', [CultureController::class, 'index'])->name('culture');
Route::get('/culture/proverbs', [CultureController::class, 'proverbs'])->name('culture.proverbs');
Route::get('/culture/proverbs/random', [CultureController::class, 'randomProverb'])->name('culture.proverb.random');
Route::get('/culture/proverbs/{word:slug}', [CultureController::class, 'proverb'])->name('culture.proverb');
Route::get('/culture/clans', [CultureController::class, 'clans'])->name('culture.clans');
Route::get('/culture/stories/{story:slug}', [CultureController::class, 'story'])->name('culture.story');
Route::get('/culture/getting-started', [CultureController::class, 'guide'])->name('culture.guide');

// The blog: public like Culture; posts by admins, comments shown once an admin approves them
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/feed.xml', [BlogController::class, 'feed'])->name('blog.feed');
Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');
Route::post('/blog/{post:slug}/comments', [BlogController::class, 'comment'])->middleware(['auth', 'throttle:6,1'])->name('blog.comment');

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
    // Phase 10: the welcome, the placement check and settings
    Route::get('/welcome', [WelcomeController::class, 'show'])->name('welcome');
    Route::post('/welcome', [WelcomeController::class, 'store']);
    Route::get('/placement', [WelcomeController::class, 'placement'])->name('placement');
    Route::post('/placement', [WelcomeController::class, 'grade'])->middleware('throttle:10,1');
    Route::get('/placement/result', [WelcomeController::class, 'result'])->name('placement.result');
    Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update']);
    Route::post('/settings/start-over', [SettingsController::class, 'startOver'])->name('settings.start-over');
    // Phase 13: the cowrie shop and the wardrobe
    Route::get('/wardrobe', [WardrobeController::class, 'show'])->name('wardrobe');
    Route::post('/wardrobe/buy/{item}', [WardrobeController::class, 'buy'])->middleware('throttle:30,1')->name('wardrobe.buy');
    Route::post('/wardrobe/wear', [WardrobeController::class, 'wear'])->middleware('throttle:60,1')->name('wardrobe.wear');
    Route::post('/wardrobe/teacher', [WardrobeController::class, 'teacher'])->middleware('throttle:30,1')->name('wardrobe.teacher');
    // Phase 12: streak freezes and daily reminders
    Route::post('/habit/freeze', [HabitController::class, 'buyFreeze'])->middleware('throttle:20,1')->name('habit.freeze');
    Route::post('/reminders', [HabitController::class, 'remind'])->middleware('throttle:20,1')->name('reminders.on');
    Route::post('/reminders/off', [HabitController::class, 'stopReminding'])->middleware('throttle:20,1')->name('reminders.off');
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
    // Phase 11: syllable guides for dictionary words (drafts until checked here)
    Route::get('/check/syllables', [Contributor\SyllableController::class, 'index'])->name('check.syllables');
    // Phase 15: culture (story pages, clans, names)
    Route::get('/check/culture', [Contributor\CultureCheckController::class, 'index'])->name('check.culture');
    Route::post('/check/culture/{type}/{id}', [Contributor\CultureCheckController::class, 'update'])
        ->whereIn('type', ['page', 'clan', 'name'])->whereNumber('id')->name('check.culture.update');
    Route::post('/check/syllables/{word}', [Contributor\SyllableController::class, 'update'])
        ->whereNumber('word')->name('check.syllables.update');
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
    // Phase 15: culture (clans, names, story pages)
    Route::get('culture', [Admin\CultureController::class, 'index'])->name('culture');
    Route::post('culture/{type}', [Admin\CultureController::class, 'store'])->whereIn('type', ['clan', 'name', 'page'])->name('culture.store');
    Route::delete('culture/{type}/{id}', [Admin\CultureController::class, 'destroy'])->whereIn('type', ['clan', 'name', 'page'])->whereNumber('id')->name('culture.destroy');
    // Phase 11: which dictionary word each word in a phrase belongs to
    Route::get('word-links', [Admin\WordLinkController::class, 'index'])->name('word-links.index');
    Route::post('word-links', [Admin\WordLinkController::class, 'store'])->name('word-links.store');
    Route::post('word-links/from-phrase/{phrase}', [Admin\WordLinkController::class, 'fromPhrase'])->name('word-links.from-phrase');
    Route::delete('word-links/{link}', [Admin\WordLinkController::class, 'destroy'])->name('word-links.destroy');
    Route::get('audio', [Admin\AudioController::class, 'index'])->name('audio.index');
    Route::post('audio/generate', [Admin\AudioController::class, 'generate'])->name('audio.generate');
    Route::post('audio/character-voice', [Admin\AudioController::class, 'chooseCharacterVoice'])->name('audio.character-voice');
    Route::get('blog', [Admin\BlogController::class, 'index'])->name('blog.index');
    Route::get('blog/create', [Admin\BlogController::class, 'create'])->name('blog.create');
    Route::post('blog', [Admin\BlogController::class, 'store'])->name('blog.store');
    Route::post('blog/preview', [Admin\BlogController::class, 'preview'])->name('blog.preview');
    Route::post('blog/image', [Admin\BlogController::class, 'image'])->middleware('throttle:30,1')->name('blog.image');
    Route::get('blog/comments', [Admin\BlogController::class, 'comments'])->name('blog.comments');
    Route::post('blog/comments/{comment}/approve', [Admin\BlogController::class, 'approve'])->name('blog.comments.approve');
    Route::post('blog/comments/{comment}/reply', [Admin\BlogController::class, 'reply'])->name('blog.comments.reply');
    Route::delete('blog/comments/{comment}', [Admin\BlogController::class, 'destroyComment'])->name('blog.comments.destroy');
    Route::get('blog/{post:id}/edit', [Admin\BlogController::class, 'edit'])->whereNumber('post')->name('blog.edit');
    Route::put('blog/{post:id}', [Admin\BlogController::class, 'update'])->whereNumber('post')->name('blog.update');
    Route::delete('blog/{post:id}', [Admin\BlogController::class, 'destroy'])->whereNumber('post')->name('blog.destroy');
    Route::get('people', [Admin\PeopleController::class, 'index'])->name('people.index');
    Route::patch('people/{user}', [Admin\PeopleController::class, 'update'])->name('people.update');
});
