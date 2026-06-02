<?php

namespace App\Support\Finance;

final readonly class BatchPaymentSnapshotCollection
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(private array $snapshot) {}

    public function primary(): array
    {
        return $this->snapshot;
    }
}
