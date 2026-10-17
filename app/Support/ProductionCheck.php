<?php

namespace App\Support;

use App\Audio\Stt\SttManager;
use App\Audio\Tts\TtsManager;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Everything that must be right before the site goes online, in plain words.
 * Each check: [status, what, how to fix]. status: ok | warn | fail. Used by app:production-check.
 */
class ProductionCheck
{
    /** @return array<int, array{0: string, 1: string, 2: string}> */
    public function run(): array
    {
        $checks = [];
        $add = function (string $status, string $what, string $fix = '') use (&$checks) {
            $checks[] = [$status, $what, $fix];
        };

        // The app itself
        $add(app()->isProduction() ? 'ok' : 'fail', 'APP_ENV is "'.app()->environment().'"', 'Set APP_ENV=production in .env.');
        $add(config('app.debug') ? 'fail' : 'ok', 'Debug pages are '.(config('app.debug') ? 'ON (shows secrets to visitors)' : 'off'), 'Set APP_DEBUG=false in .env.');
        $add(filled(config('app.key')) ? 'ok' : 'fail', 'APP_KEY is '.(filled(config('app.key')) ? 'set' : 'missing'), 'Run php artisan key:generate (only once, on a new install).');
        $https = str_starts_with((string) config('app.url'), 'https://');
        $add($https ? 'ok' : 'fail', 'APP_URL is '.config('app.url'), 'Use https:// : browsers only allow the microphone (Studio, speaking practice) on HTTPS.');
        $add(! $https || config('session.secure') ? 'ok' : 'warn', 'Session cookie sent over HTTPS only: '.(config('session.secure') ? 'yes' : 'no'), 'Set SESSION_SECURE_COOKIE=true once the site is on HTTPS.');

        // Front-end files
        $add(! file_exists(public_path('hot')) ? 'ok' : 'fail', 'Vite dev server file public/hot '.(file_exists(public_path('hot')) ? 'is present' : 'is absent'), 'Stop "npm run dev" and delete public/hot: with it, pages load scripts from your own computer.');
        $add(file_exists(public_path('build/manifest.json')) ? 'ok' : 'fail', 'Built CSS/JS (public/build) '.(file_exists(public_path('build/manifest.json')) ? 'found' : 'missing'), 'Run npm run build.');
        $add(file_exists(public_path('storage')) ? 'ok' : 'fail', 'Recordings link public/storage '.(file_exists(public_path('storage')) ? 'exists' : 'is missing'), 'Run php artisan storage:link.');
        foreach ([storage_path(), base_path('bootstrap/cache')] as $dir) {
            $add(is_writable($dir) ? 'ok' : 'fail', str_replace(base_path().'/', '', $dir).' is '.(is_writable($dir) ? 'writable' : 'NOT writable'), 'Let the web server user (www-data) write there: sudo chown -R www-data:www-data storage bootstrap/cache');
        }

        // Database
        try {
            DB::connection()->getPdo();
            $add('ok', 'Database '.config('database.default').' connects');
            $pending = $this->pendingMigrations();
            $add($pending ? 'fail' : 'ok', $pending ? count($pending).' migration(s) not run yet' : 'All migrations have run', 'Run php artisan migrate --force.');
            $admins = User::where('is_admin', true)->count();
            $add($admins ? 'ok' : 'warn', "{$admins} admin account(s)", 'php artisan app:make-admin you@example.com');
        } catch (\Throwable $e) {
            $add('fail', 'Database does not connect: '.str($e->getMessage())->limit(120), 'Check DB_* in .env and that PostgreSQL is running.');
        }

        // Voices and speaking
        $tts = app(TtsManager::class)->provider();
        $add(! config('luganda.voice') || $tts->name() !== 'fake' ? 'ok' : 'warn', 'Generated voices: '.$tts->label(), 'Set LUGANDA_TTS=sunbird (test beeps are not Luganda).');
        $add($tts->available() ? 'ok' : 'fail', $tts->label().($tts->available() ? ' is set up' : ' is not set up'), 'Add SUNBIRD_API_TOKEN to .env.');
        $stt = app(SttManager::class)->provider();
        $add($stt?->name() === 'fake' ? 'warn' : 'ok', 'Automatic speech checking: '.($stt ? $stt->label() : 'off'), 'Use LUGANDA_STT=off or sunbird; "fake" is only for tests.');

        return $checks;
    }

    /** @return string[] */
    private function pendingMigrations(): array
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return ['(no migrations table)'];
        }
        $ran = $migrator->getRepository()->getRan();
        $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));

        return array_values(array_diff($files, $ran));
    }
}
