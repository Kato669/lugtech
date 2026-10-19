<?php

use App\Audio\Tts\TtsManager;
use App\Lessons\LessonContext;
use App\Lessons\ScriptedLesson;
use App\Models\AudioClip;
use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\SpeechAttempt;
use App\Models\User;
use App\Models\Word;
use App\Services\AudioGenerator;
use App\Services\DailyReminders;
use App\Services\LessonPlan;
use App\Services\WebPush;
use App\Support\LegacyAudioImport;
use App\Support\ProductionCheck;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:make-admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error("No user with email {$email}. Register in the app first.");

        return 1;
    }
    $user->forceFill(['is_admin' => true])->save();
    $this->info("{$user->name} is now an admin.");
})->purpose('Give an existing user access to the admin panel');

Artisan::command('app:make-reviewer {email}', function (string $email) {
    $user = User::where('email', $email)->where('is_guest', false)->first();
    if (! $user) {
        $this->error("No registered user with email {$email}. Ask them to create an account first.");

        return 1;
    }
    $user->forceFill(['is_reviewer' => true])->save();
    $this->info("{$user->name} can now use the recording studio and check page.");
})->purpose('Let a native speaker record audio and review Luganda');

Artisan::command('app:prune-guests {--days=60}', function (int $days) {
    $count = User::where('is_guest', true)->where('updated_at', '<', now()->subDays($days))
        ->where(fn ($q) => $q->whereNull('last_active_on')->orWhere('last_active_on', '<', now()->subDays($days)))
        ->delete();
    $this->info("Removed {$count} inactive guest accounts.");
})->purpose('Delete guest accounts that have not practised for a while');

Artisan::command('app:audio-report {--lesson= : Only this lesson (slug)} {--words : Also list dictionary words}', function () {
    $state = function ($item, string $slot): string {
        $clips = $item->currentClips->where('slot', $slot);
        foreach (['approved' => 'approved', 'pending' => 'pending', 'flagged' => 'FLAGGED'] as $status => $label) {
            if ($c = $clips->firstWhere('status', $status)) {
                return $label.($c->isGenerated() ? ' (gen)' : '').($c->matchesText($item->luganda) ? '' : ' (old text)');
            }
        }

        return '-';
    };
    $lessons = Lesson::with(['stage', 'phrases.currentClips'])->get()->sortBy(fn ($l) => [$l->stage->position, $l->position])
        ->when($this->option('lesson'), fn ($c, $slug) => $c->where('slug', $slug));
    foreach ($lessons as $lesson) {
        $this->line('');
        $this->info("Stage {$lesson->stage->position} · {$lesson->title} ({$lesson->slug})");
        $this->table(['Code', 'Luganda', 'Text', 'Normal', 'Slow', 'Mukasa', 'Nakato'], $lesson->phrases->map(fn ($p) => [
            $p->code, $p->luganda, $p->is_reviewed ? 'approved' : ($p->review_note ? 'FLAGGED' : 'draft'),
            $state($p, 'normal'), $state($p, 'slow'), $state($p, 'male'), $state($p, 'female'),
        ]));
    }
    if ($this->option('words')) {
        $this->info('Dictionary');
        $this->table(['Code', 'Luganda', 'Text', 'Recording'], Word::with('currentClips')->orderBy('luganda')->get()
            ->map(fn ($w) => [$w->code, $w->luganda, $w->is_reviewed ? 'approved' : 'draft', $state($w, 'normal')]));
    }
    $counts = AudioClip::current()->selectRaw('status, source, count(*) as n')->groupBy('status', 'source')->get();
    $this->line('');
    $this->info('Recordings in use: '.($counts->map(fn ($r) => "{$r->n} {$r->status} ({$r->source})")->implode(', ') ?: 'none yet'));
    $this->line('Voices are '.(config('luganda.voice') ? 'ON' : 'OFF (LUGANDA_VOICE=false): learners hear nothing until you switch them on').'.');
})->purpose('Show which phrases and words have approved, pending, flagged or missing audio');

Artisan::command('app:generate-audio
    {--lesson=* : Lesson slug(s), e.g. ennamba-1-5}
    {--code=* : Phrase or word code(s), e.g. P0001 W0012}
    {--words : All dictionary words and proverbs}
    {--all : Every lesson phrase, dictionary word and proverb}
    {--pause=500 : Milliseconds to wait between requests (be gentle with the service)}
    {--provider= : fake | sunbird (default: LUGANDA_TTS)}
    {--voice= : Provider voice, e.g. salt_lug_0001}
    {--limit=10 : At most this many in one run}
    {--force : Generate again even if this voice was already done}
    {--dry-run : Only show what would happen}', function (AudioGenerator $generator, TtsManager $tts) {
    $items = collect();
    foreach ($this->option('lesson') as $slug) {
        $lesson = Lesson::where('slug', $slug)->first();
        $lesson ? $items->push(...$lesson->phrases()->get()) : $this->warn("No lesson \"{$slug}\".");
    }
    foreach ($this->option('code') as $code) {
        preg_match('/^([PW])0*(\d+)$/i', trim($code), $m);
        $item = $m ? (strtoupper($m[1]) === 'P' ? Phrase::find($m[2]) : Word::find($m[2])) : null;
        $item ? $items->push($item) : $this->warn("No phrase or word \"{$code}\".");
    }
    if ($this->option('all')) {
        Lesson::with('stage')->get()->sortBy(fn ($l) => [$l->stage->position, $l->position])
            ->each(fn ($l) => $items->push(...$l->phrases()->get()));
        $items->push(...Phrase::orderBy('id')->get()); // phrases not in a lesson yet
    }
    if ($this->option('words') || $this->option('all')) {
        $items->push(...Word::orderBy('kind')->orderBy('luganda')->get());
    }
    if ($items->isEmpty()) {
        $this->error('Say what to generate: --all, --lesson=ennamba-1-5, --code=P0001 or --words.');

        return 1;
    }

    $provider = $tts->provider($this->option('provider'));
    $voice = $this->option('voice') ?: $provider->defaultVoice();
    $limit = max(1, (int) $this->option('limit'));
    $items = $items->unique(fn ($i) => $i->getMorphClass().$i->id)->values();
    $this->info(($this->option('dry-run') ? '[dry run] ' : '')."{$provider->label()} · {$voice} · up to {$limit} of {$items->count()} item(s) · {$generator->remainingToday()} left today");

    $made = 0;
    foreach ($items as $item) {
        if ($made >= $limit) {
            $this->line("Stopped at --limit={$limit}.");
            break;
        }
        $r = $generator->generate($item, $provider->name(), $voice, 'normal', null, (bool) $this->option('force'), (bool) $this->option('dry-run'));
        $mark = ['created' => '<info>✓</info>', 'would_create' => '•', 'skipped' => '-', 'limit' => '<error>!</error>', 'failed' => '<error>✗</error>'][$r['status']];
        $this->line("{$mark} {$item->code} {$item->luganda}: {$r['message']}");
        if (in_array($r['status'], ['created', 'would_create'], true)) {
            $made++;
            if ($r['status'] === 'created' && (int) $this->option('pause') > 0) {
                usleep((int) $this->option('pause') * 1000);
            }
        }
        if ($r['status'] === 'limit') {
            break;
        }
    }
    $this->info($this->option('dry-run') ? "{$made} would be generated." : "{$made} generated. Listen and approve them on the Check page (🔊 Recordings to check).");
})->purpose('Generate draft recordings with a text-to-speech voice (they wait for approval)');

Artisan::command('app:tts-voices {--provider=}', function (TtsManager $tts) {
    $p = $tts->provider($this->option('provider'));
    if (! $p->available()) {
        $this->error("{$p->label()} is not set up.");

        return 1;
    }
    $this->info("{$p->label()} voices for Luganda (default: {$p->defaultVoice()}):");
    foreach ($p->voices() as $v) {
        $this->line("  {$v}");
    }
})->purpose('List the Luganda voices of a text-to-speech provider');

Artisan::command('app:lesson-check {slug? : One lesson (default: every scripted lesson)}', function () {
    $lessons = Lesson::query()->when($this->argument('slug'), fn ($q, $slug) => $q->where('slug', $slug), fn ($q) => $q->where('mode', 'scripted'))->get();
    if ($lessons->isEmpty()) {
        $this->info($this->argument('slug') ? 'No such lesson.' : 'No scripted lessons yet: every lesson is generated automatically from its phrases.');

        return $this->argument('slug') ? 1 : 0;
    }
    $bad = 0;
    foreach ($lessons as $lesson) {
        $ctx = new LessonContext($lesson, $lesson->phrases()->get());
        $this->info("{$lesson->title} ({$lesson->slug}) · ".($lesson->isScripted() ? 'scripted' : 'automatic'));
        foreach ($lesson->steps as $step) {
            $problems = app(ScriptedLesson::class)->problems($step, $ctx);
            $bad += $problems ? 1 : 0;
            $this->line(sprintf('  %2d. %-9s %s', $step->position, $step->type, $problems ? '<error>'.implode('; ', $problems).'</error>' : '<info>ok</info>'));
        }
        $this->line('  Plays as '.count((new LessonPlan)->build($lesson)).' steps for learners.');
    }

    return $bad ? 1 : 0;
})->purpose('Check the steps of scripted lessons for problems');

Artisan::command('app:lesson-mode {slug} {mode : auto | scripted}', function (string $slug, string $mode) {
    $lesson = Lesson::where('slug', $slug)->first();
    if (! $lesson || ! in_array($mode, ['auto', 'scripted'], true)) {
        $this->error($lesson ? 'Mode must be "auto" or "scripted".' : "No lesson \"{$slug}\".");

        return 1;
    }
    if ($mode === 'scripted' && ! $lesson->steps()->exists()) {
        $this->error('This lesson has no steps yet.');

        return 1;
    }
    $lesson->forceFill(['mode' => $mode])->save();
    $this->info("{$lesson->title} is now ".($mode === 'auto' ? 'automatic (its steps are kept)' : 'scripted').'.');
})->purpose('Switch a lesson between its scripted steps and the automatic exercises');

Artisan::command('app:import-legacy-audio', function () {
    $n = LegacyAudioImport::run();
    $this->info($n ? "Imported {$n} recordings from the old audio columns." : 'Nothing new to import.');
})->purpose('Copy recordings stored in the old audio_* columns into audio_clips (safe to repeat)');

Artisan::command('app:speaking-report {--days=30 : Only attempts from the last N days} {--limit=25 : Phrases to list}', function () {
    $since = now()->subDays((int) $this->option('days'));
    $rows = SpeechAttempt::query()->where('created_at', '>=', $since)
        ->selectRaw("phrase_id, COUNT(*) AS tries, COUNT(DISTINCT user_id) AS learners,
            SUM(CASE WHEN self_rating = 'good' THEN 1 ELSE 0 END) AS good,
            SUM(CASE WHEN self_rating = 'again' THEN 1 ELSE 0 END) AS again,
            AVG(score) AS avg_score, COUNT(score) AS checked")
        ->groupBy('phrase_id')->get();
    if ($rows->isEmpty()) {
        $this->info('No speaking practice in the last '.$this->option('days').' days yet.');

        return 0;
    }
    $phrases = Phrase::whereIn('id', $rows->pluck('phrase_id'))->pluck('luganda', 'id');
    // Hardest first: the biggest share of "not yet" among the learner's own verdicts.
    $sorted = $rows->sortByDesc(fn ($r) => [($r->good + $r->again) ? $r->again / ($r->good + $r->again) : 0, $r->tries])->take((int) $this->option('limit'));
    $this->table(['Phrase', 'Tries', 'Learners', 'Sounded the same', 'Not yet', 'Auto-checked', 'Avg score'], $sorted->map(fn ($r) => [
        $phrases[$r->phrase_id] ?? "#{$r->phrase_id}", $r->tries, $r->learners,
        ($r->good + $r->again) ? round(100 * $r->good / ($r->good + $r->again)).'%' : '-',
        (int) $r->again, (int) $r->checked, $r->checked ? (int) round($r->avg_score) : '-',
    ])->values()->all());
    $this->line('Total: '.$rows->sum('tries').' tries by '.SpeechAttempt::where('created_at', '>=', $since)->distinct()->count('user_id').' learners. (Learners\' own audio is never stored.)');

    return 0;
})->purpose('Which phrases learners find hard to say (from speaking practice)');

Artisan::command('app:production-check', function () {
    $icons = ['ok' => '<info>✓</info>', 'warn' => '<comment>!</comment>', 'fail' => '<error>✗</error>'];
    $checks = app(ProductionCheck::class)->run();
    foreach ($checks as [$status, $what, $fix]) {
        $this->line("  {$icons[$status]} {$what}".($status !== 'ok' && $fix ? "\n      → {$fix}" : ''));
    }
    $this->newLine();
    $this->line('  Also, outside the app: a daily database backup (pg_dump) and a copy of storage/app/public (recordings),');
    $this->line('  and the scheduler in cron (prunes old guest accounts weekly):');
    $this->line('  * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');
    $fails = collect($checks)->where(0, 'fail')->count();
    $fails ? $this->error("  {$fails} thing(s) to fix before going online.") : $this->info('  Ready to go online.');

    return $fails ? 1 : 0;
})->purpose('Check the settings that matter before the site goes online');

Artisan::command('app:push-keys', function () {
    $keys = WebPush::generateKeys();
    $this->line('Add these lines to .env (keep the private key secret), then run php artisan config:clear:');
    $this->newLine();
    $this->line('LUGANDA_PUSH_PUBLIC_KEY='.$keys['public']);
    $this->line('LUGANDA_PUSH_PRIVATE_KEY='.$keys['private']);
    $this->line('LUGANDA_PUSH_SUBJECT=mailto:you@example.com');
    $this->newLine();
    $this->line('Reminders need the site on HTTPS, and the scheduler in cron (php artisan schedule:run every minute).');
    $this->line('Making new keys later switches off every reminder already set up: learners would turn them on again.');

    return 0;
})->purpose('Make the keys for daily reminders (web push)');

Artisan::command('app:send-reminders {--dry-run : Say who would get one, without sending}', function () {
    if (! WebPush::configured()) {
        $this->warn('Reminders are not set up: run php artisan app:push-keys first.');

        return 0;
    }
    $stats = app(DailyReminders::class)->run(dryRun: (bool) $this->option('dry-run'));
    $this->line(($this->option('dry-run') ? 'Would send: ' : 'Sent: ').$stats['sent'].' · skipped: '.$stats['skipped'].' · removed: '.$stats['removed'].' · failed: '.$stats['failed']);

    return 0;
})->purpose('Send the daily reminders due this hour (Phase 12)');

Schedule::command('app:prune-guests')->weekly();
Schedule::command('app:send-reminders')->hourly(); // Phase 12: daily reminders, only if keys are set
