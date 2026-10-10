<?php

namespace Caiyun\Sms\Exceptions;

use RuntimeException;
use Throwable;

class SmsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $providerCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
