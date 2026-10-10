<?php

namespace Caiyun\Sms\Contracts;

use Caiyun\Sms\SmsResult;

interface SmsSender
{
    /**
     * 发送模板短信。
     *
     * @param  array<array-key, scalar|null>  $parameters
     */
    public function send(string $mobile, string $template, array $parameters = []): SmsResult;
}
