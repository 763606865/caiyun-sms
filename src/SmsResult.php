<?php

namespace Caiyun\Sms;

final readonly class SmsResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $driver,
        public ?string $requestId = null,
        public ?string $messageId = null,
        public array $raw = [],
    ) {}
}
