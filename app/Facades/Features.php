<?php

namespace App\Facades;

use App\Features\Feature;
use App\Features\FeatureManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool active(Feature $feature)
 * @method static mixed value(Feature $feature)
 * @method static bool isInitialized(Feature $feature)
 * @method static \App\Models\AppFeature|null record(Feature $feature)
 * @method static list<Feature> pending()
 * @method static list<Feature> initialize(array $values, string $source)
 * @method static void change(Feature $feature, bool $value, string $reason, string $actor)
 * @method static array<string, bool> all()
 * @method static string|null presetName()
 * @method static array|null preset(string $name)
 * @method static void flush()
 *
 * @see FeatureManager
 */
class Features extends Facade
{
    // FeatureManager is scoped (flushed per Octane request and per queued job);
    // a cached facade instance would outlive that and serve stale flags.
    protected static $cached = false;

    protected static function getFacadeAccessor(): string
    {
        return FeatureManager::class;
    }
}
