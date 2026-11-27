<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;

class ProductionCheckCommand extends Command
{
    protected $signature = 'app:production-check';

    protected $description = 'Validate production readiness (env, security, assets, Redis)';

    public function handle(): int
    {
        $ok = true;
        $this->info('Fuel Prices PK — production check');
        $this->newLine();

        $checks = [
            'APP_ENV is production' => app()->environment('production'),
            'APP_DEBUG is false' => config('app.debug') === false,
            'APP_KEY set' => filled(config('app.key')),
            'APP_URL is https' => str_starts_with((string) config('app.url'), 'https://'),
            'FORCE_HTTPS enabled' => (bool) config('app.force_https'),
            'DB is mysql' => config('database.default') === 'mysql',
            'QUEUE uses redis' => config('queue.default') === 'redis',
            'CACHE uses redis' => config('cache.default') === 'redis',
            'SESSION uses redis' => config('session.driver') === 'redis',
            'SESSION_SECURE_COOKIE' => (bool) config('session.secure'),
            'Vite build present' => File::exists(public_path('build/manifest.json')),
            'No public/hot (dev server)' => ! File::exists(public_path('hot')),
        ];

        foreach ($checks as $label => $pass) {
            $this->line(($pass ? '<info>✓</info>' : '<error>✗</error>')." {$label}");
            $ok = $ok && $pass;
        }

        try {
            DB::connection()->getPdo();
            $this->line('<info>✓</info> Database connection');
        } catch (\Throwable $e) {
            $this->line('<error>✗</error> Database connection: '.$e->getMessage());
            $ok = false;
        }

        if (config('queue.default') === 'redis' || config('cache.default') === 'redis') {
            try {
                Redis::connection()->ping();
                $this->line('<info>✓</info> Redis connection');
            } catch (\Throwable $e) {
                $this->line('<error>✗</error> Redis: '.$e->getMessage());
                $ok = false;
            }
        }

        if (config('firebase.fcm_enabled')) {
            $fcmOk = filled(config('firebase.project_id'))
                && (filled(config('firebase.client_email')) || filled(config('firebase.credentials')));
            $this->line(($fcmOk ? '<info>✓</info>' : '<error>✗</error>').' FCM credentials');
            $ok = $ok && $fcmOk;
        } else {
            $this->line('<comment>·</comment> FCM disabled (ok for web-only launch)');
        }

        $this->newLine();
        if ($ok) {
            $this->info('Ready for production.');

            return self::SUCCESS;
        }

        $this->error('Not ready — fix the items marked ✗. See docs/CLOUD.md');

        return self::FAILURE;
    }
}
