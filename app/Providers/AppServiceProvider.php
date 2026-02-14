<?php

namespace App\Providers;

use App\Policies\AccountSettingsPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register pass generation services as singletons
        $this->app->singleton(\App\Services\ApplePassService::class);
        $this->app->singleton(\App\Services\GooglePassService::class);
        $this->app->singleton(\App\Services\PassLimitService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('access-account-settings', [AccountSettingsPolicy::class, 'access']);

        Queue::failing(function ($event) {
            $job = $event->job;
            $payload = method_exists($job, 'payload') ? $job->payload() : [];

            Log::error('Queue job failed', [
                'job_name' => $payload['displayName'] ?? get_class($job),
                'queue' => $job->getQueue(),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
