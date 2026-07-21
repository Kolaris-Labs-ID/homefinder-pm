<?php

namespace App\Exceptions\Hris;

use App\Models\Hris\OvertimePayBatch;
use RuntimeException;

class OvertimeBatchLockedException extends RuntimeException
{
    public function __construct(public OvertimePayBatch $batch)
    {
        parent::__construct(
            "Overtime pay batch #{$batch->id} is {$batch->status} and cannot be recalculated. "
            .'Settle rate corrections for locked or paid batches as a separate adjustment.'
        );
    }
}
