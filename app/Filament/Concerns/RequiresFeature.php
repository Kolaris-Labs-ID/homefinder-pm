<?php

namespace App\Filament\Concerns;

use App\Facades\Features;
use App\Features\Feature;

/**
 * For a Filament resource or page that belongs to a feature: with the feature
 * off it has no navigation entry, no global search and its URLs answer 403.
 */
trait RequiresFeature
{
    abstract public static function requiredFeature(): Feature;

    public static function canAccess(array $parameters = []): bool
    {
        return Features::active(static::requiredFeature()) && parent::canAccess();
    }
}
