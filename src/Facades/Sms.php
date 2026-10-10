<?php

namespace Caiyun\Sms\Facades;

use Caiyun\Sms\Contracts\SmsSender;
use Caiyun\Sms\SmsManager;
use Caiyun\Sms\SmsResult;
use Illuminate\Support\Facades\Facade;

/**
 * @method static SmsResult send(string $mobile, string $template, array<array-key, scalar|null> $parameters = [])
 * @method static SmsSender driver(?string $driver = null)
 * @method static string getDefaultDriver()
 * @method static SmsManager extend(string $driver, \Closure $callback)
 *
 * @see SmsManager
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sms';
    }
}
