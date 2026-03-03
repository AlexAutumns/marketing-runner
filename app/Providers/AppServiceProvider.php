<?php

namespace App\Providers;

use App\Contracts\MarketingMailer;
use App\Contracts\MarketingStorage;
use App\Services\Mail\LaravelSmtpMarketingMailer;
use App\Services\Mail\LogMarketingMailer;
use App\Services\Storage\DemoSqliteMarketingStorage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $mode = env('MAIL_DRIVER_MODE', 'log');

        $this->app->bind(MarketingMailer::class, function () use ($mode) {
            return $mode === 'smtp'
                ? new LaravelSmtpMarketingMailer
                : new LogMarketingMailer;
        });

        $this->app->bind(MarketingStorage::class, function () {
            return new DemoSqliteMarketingStorage;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
