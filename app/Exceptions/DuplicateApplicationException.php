<?php

namespace App\Exceptions;

use RuntimeException;

class DuplicateApplicationException extends RuntimeException
{
    public function __construct(string $message = 'You have already applied for this position.')
    {
        parent::__construct($message);
    }
}
