<?php

namespace Caiyun\Sms\Drivers;

use Caiyun\Sms\Contracts\SmsSender;
use Caiyun\Sms\SmsResult;
use Illuminate\Log\LogManager;

class LogDriver implements SmsSender
{
    public function __construct(
        private readonly LogManager $log,
        private readonly ?string $channel = null,
    ) {}

    public function send(string $mobile, string $template, array $parameters = []): SmsResult
    {
        $this->log->channel($this->channel)->info('SMS message', [
            'mobile' => $mobile,
            'template' => $template,
            'parameters' => $parameters,
        ]);

        return new SmsResult(
            driver: 'log',
            messageId: 'log-'.bin2hex(random_bytes(8)),
        );
    }
}
