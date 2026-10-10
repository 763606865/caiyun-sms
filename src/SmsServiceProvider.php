<?php

namespace Caiyun\Sms;

use Caiyun\Sms\Contracts\SmsSender;
use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sms.php', 'sms');

        $this->app->singleton(SmsManager::class);
        $this->app->alias(SmsManager::class, 'sms');
        $this->app->alias(SmsManager::class, SmsSender::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                realpath(__DIR__.'/../config/sms.php') => $this->app->configPath('sms.php'),
            ], 'sms-config');
        }
    }
}
