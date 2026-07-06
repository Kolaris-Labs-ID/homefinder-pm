<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientLeaveBalanceException extends RuntimeException
{
    public function __construct(string $message = 'Insufficient leave balance for this request.')
    {
        parent::__construct($message);
    }
}
