<?php

namespace App\Exceptions\Hris;

use RuntimeException;

class OvertimeRateCoverageException extends RuntimeException
{
    /** @param array<int,array{employee_id:int,employee_name:?string,work_date:string,overtime_log_id:?int,attendance_log_id:?int}> $gaps */
    public function __construct(public array $gaps)
    {
        $lines = array_map(function ($gap) {
            $source = $gap['overtime_log_id']
                ? "overtime log #{$gap['overtime_log_id']}"
                : "weekend/holiday attendance #{$gap['attendance_log_id']}";

            return "employee #{$gap['employee_id']} on {$gap['work_date']} ({$source})";
        }, $gaps);

        parent::__construct(
            'Overtime rate cannot be resolved — no salary structure with a positive basic salary '
            .'or daily allowance rate is effective on the work date for: '
            .implode('; ', $lines)
            .'. Add or correct a salary structure effective on or before each date, then regenerate.'
        );
    }
}
