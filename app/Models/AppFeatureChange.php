<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Audit row for a locked feature flag changed after first setup. */
class AppFeatureChange extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['key', 'old_value', 'new_value', 'reason', 'actor'];

    protected $casts = [
        'old_value' => 'json',
        'new_value' => 'json',
    ];
}
