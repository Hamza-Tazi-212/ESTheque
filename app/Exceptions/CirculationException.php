<?php

namespace App\Exceptions;

use RuntimeException;

class CirculationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function body(): array
    {
        return ['message' => $this->getMessage(), 'error' => $this->errorCode];
    }
}
