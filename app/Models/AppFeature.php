<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One company's first choice for one feature flag. See App\Features\FeatureManager.
 *
 * @property string $key
 * @property mixed $value
 * @property string $source preset:<name>, user:<id> or cli
 * @property \Illuminate\Support\Carbon $initialized_at
 */
class AppFeature extends Model
{
    protected $fillable = ['key', 'value', 'source', 'initialized_at'];

    protected $casts = [
        'value' => 'json',
        'initialized_at' => 'datetime',
    ];
}
