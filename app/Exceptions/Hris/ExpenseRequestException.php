<?php

namespace App\Exceptions\Hris;

/**
 * Business-rule violation in the ExpenseRequest lifecycle (missing evidence,
 * invalid state transition, ...). Lets the API return a safe 422 for expected
 * failures while genuine infrastructure faults propagate as logged 500s.
 * Extends RuntimeException for backward compatibility with existing catchers.
 */
class ExpenseRequestException extends \RuntimeException {}
