<?php

namespace Caiyun\Sms;

use Caiyun\Sms\Contracts\SmsSender;
use Caiyun\Sms\Drivers\AliyunDriver;
use Caiyun\Sms\Drivers\CloopenDriver;
use Caiyun\Sms\Drivers\HuaweiDriver;
use Caiyun\Sms\Drivers\LogDriver;
use Caiyun\Sms\Drivers\TencentDriver;
use Caiyun\Sms\Drivers\VolcengineDriver;
use Caiyun\Sms\Exceptions\SmsException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Log\LogManager;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SmsManager extends Manager implements SmsSender
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('sms.default', 'log');
    }

    public function send(string $mobile, string $template, array $parameters = []): SmsResult
    {
        return $this->driver()->send($mobile, $template, $parameters);
    }

    public function driver($driver = null): SmsSender
    {
        return parent::driver($driver);
    }

    protected function createDriver($driver): SmsSender
    {
        $config = $this->driverConfig($driver);
        $type = $config['driver'] ?? $driver;

        if (! is_string($type) || $type === '') {
            throw new SmsException("SMS driver [{$driver}] has an invalid driver type.");
        }

        if (isset($this->customCreators[$driver])) {
            $sender = $this->callCustomCreator($driver);
        } elseif (isset($this->customCreators[$type])) {
            $sender = $this->callCustomCreator($type);
        } else {
            $method = 'create'.Str::studly($type).'Driver';

            if (! method_exists($this, $method)) {
                throw new InvalidArgumentException("SMS driver [{$driver}] is not supported.");
            }

            $sender = $this->{$method}($config);
        }

        if (! $sender instanceof SmsSender) {
            throw new SmsException("SMS driver [{$driver}] must implement ".SmsSender::class.'.');
        }

        return new TemplateSender(
            $sender,
            fn (string $template): string => $this->resolveTemplate($template, $driver, $type),
        );
    }

    /** @param array<string, mixed> $config */
    protected function createLogDriver(array $config): SmsSender
    {
        return new LogDriver(
            $this->container->make(LogManager::class),
            $config['channel'] ?? null,
        );
    }

    /** @param array<string, mixed> $config */
    protected function createAliyunDriver(array $config): SmsSender
    {
        return new AliyunDriver($config);
    }

    /** @param array<string, mixed> $config */
    protected function createTencentDriver(array $config): SmsSender
    {
        return new TencentDriver($config);
    }

    /** @param array<string, mixed> $config */
    protected function createHuaweiDriver(array $config): SmsSender
    {
        return new HuaweiDriver($config);
    }

    /** @param array<string, mixed> $config */
    protected function createVolcengineDriver(array $config): SmsSender
    {
        return new VolcengineDriver($config);
    }

    /** @param array<string, mixed> $config */
    protected function createCloopenDriver(array $config): SmsSender
    {
        return new CloopenDriver($config);
    }

    private function resolveTemplate(string $template, string $driver, string $type): string
    {
        $templateId = $this->config->get("sms.templates.{$template}", $template);

        if (is_array($templateId)) {
            $templateId = $templateId[$driver] ?? $templateId[$type] ?? null;
        }

        if (! is_string($templateId) || $templateId === '') {
            throw new SmsException("SMS template [{$template}] is not configured for driver [{$driver}].");
        }

        return $templateId;
    }

    /**
     * @return array<string, mixed>
     */
    private function driverConfig(string $driver): array
    {
        $config = $this->config->get("sms.drivers.{$driver}", []);

        return is_array($config) ? $config : [];
    }
}
