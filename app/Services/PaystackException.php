<?php

namespace App\Services;

use RuntimeException;

class PaystackException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $providerMessage = null,
    ) {
        parent::__construct($message);
    }

    public function isInvalidSubaccount(): bool
    {
        return $this->statusCode === 404
            && is_string($this->providerMessage)
            && str_contains(strtolower($this->providerMessage), 'invalid subaccount');
    }
}
