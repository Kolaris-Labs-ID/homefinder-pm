<?php

namespace App\Contracts\Hris;

interface PayableBatchSource
{
    public function paymentSourceType(): string;

    public function paymentSourceId(): string;
}
