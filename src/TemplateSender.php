<?php

namespace Caiyun\Sms;

use Caiyun\Sms\Contracts\SmsSender;
use Closure;

/** @internal */
final class TemplateSender implements SmsSender
{
    /**
     * @param  Closure(string): string  $resolveTemplate
     */
    public function __construct(
        private readonly SmsSender $sender,
        private readonly Closure $resolveTemplate,
    ) {}

    public function send(string $mobile, string $template, array $parameters = []): SmsResult
    {
        return $this->sender->send($mobile, ($this->resolveTemplate)($template), $parameters);
    }
}
