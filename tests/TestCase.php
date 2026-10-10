<?php

namespace Caiyun\Sms\Tests;

use Caiyun\Sms\SmsServiceProvider;
use Illuminate\Foundation\Application;
use LogicException;
use Monolog\Handler\TestHandler;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function application(): Application
    {
        if ($this->app === null) {
            throw new LogicException('The test application has not been initialized.');
        }

        return $this->app;
    }

    protected function getPackageProviders($app): array
    {
        return [SmsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('logging.default', 'sms-test');
        $app['config']->set('logging.channels.sms-test', [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
        ]);
    }
}
