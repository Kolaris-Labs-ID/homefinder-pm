<?php

namespace App\Exceptions\Hris;

use RuntimeException;

class PayrollCoverageException extends RuntimeException
{
    /** @param array<int,array{employee_id:int,employee_name:?string,first_working_day:string}> $gaps */
    public function __construct(public array $gaps)
    {
        $ids = implode(', ', array_column($gaps, 'employee_id'));
        parent::__construct("Payroll salary coverage incomplete for employee(s): {$ids}. Every eligible salary date must resolve to a salary structure.");
    }
}
