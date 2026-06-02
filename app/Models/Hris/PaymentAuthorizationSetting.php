<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAuthorizationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_signer_1_user_id',
        'finance_signer_2_user_id',
        'executive_signer_user_id',
        'require_two_finance_signatures',
        'require_executive_signature',
        'high_value_threshold',
        'allow_creator_to_sign',
        'allow_executor_to_sign',
    ];

    protected $casts = [
        'require_two_finance_signatures' => 'boolean',
        'require_executive_signature' => 'boolean',
        'allow_creator_to_sign' => 'boolean',
        'allow_executor_to_sign' => 'boolean',
        'high_value_threshold' => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'require_two_finance_signatures' => true,
            'require_executive_signature' => true,
            'high_value_threshold' => '10000000.00',
            'allow_creator_to_sign' => false,
            'allow_executor_to_sign' => false,
        ]);
    }

    public function financeSigner1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_signer_1_user_id');
    }

    public function financeSigner2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_signer_2_user_id');
    }

    public function executiveSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executive_signer_user_id');
    }
}
