<?php

namespace App\Exceptions;

use RuntimeException;

class ImmutableScreeningResultException extends RuntimeException
{
    public function __construct(string $message = 'Screening results are immutable; create a new version instead.')
    {
        parent::__construct($message);
    }
}
