<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatePayCompensationPartialPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'late_pay_compensation_item_id',
        'payment_date',
        'paid_percent',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'paid_percent' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(LatePayCompensationItem::class, 'late_pay_compensation_item_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $partial) {
            $item = $partial->item ?? LatePayCompensationItem::find($partial->late_pay_compensation_item_id);

            if (! $item) {
                return;
            }

            $existingTotal = $item->partialPayments()
                ->when($partial->exists, fn ($q) => $q->where('id', '!=', $partial->id))
                ->sum('paid_percent');

            $newTotal = $existingTotal + (float) $partial->paid_percent;

            if ($newTotal > 100) {
                throw new \Exception(
                    "Total paid percent would be {$newTotal}%, which exceeds 100%. Current total (excl. this record): {$existingTotal}%."
                );
            }

            $batch = $item->batch;

            if ($batch) {
                $scheduled = $batch->scheduled_pay_date->copy()->startOfDay();
                $actual = $batch->actual_pay_date->copy()->startOfDay();
                $paymentDate = $partial->payment_date->copy()->startOfDay();

                if ($paymentDate->lt($scheduled) || $paymentDate->gt($actual)) {
                    throw new \Exception(
                        "Payment date must be between scheduled pay date ({$scheduled->format('Y-m-d')}) and actual pay date ({$actual->format('Y-m-d')})."
                    );
                }
            }
        });
    }
}
