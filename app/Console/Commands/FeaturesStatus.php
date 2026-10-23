<?php

namespace App\Console\Commands;

use App\Features\Feature;
use App\Features\FeatureManager;
use Illuminate\Console\Command;

class FeaturesStatus extends Command
{
    protected $signature = 'features:status';

    protected $description = 'Show each company feature flag, its value and where the value came from';

    public function handle(FeatureManager $features): int
    {
        $preset = $features->presetName();

        $this->table(
            ['Key', 'Feature', 'Value', 'Set', 'Source'],
            collect(Feature::cases())->map(function (Feature $feature) use ($features, $preset) {
                $record = $features->record($feature);

                return [
                    $feature->value,
                    $feature->label(),
                    $features->active($feature) ? 'on' : 'off',
                    $record?->initialized_at?->toDateTimeString() ?? 'not set',
                    $record?->source ?? ($preset !== null ? "preset {$preset} (not stored)" : 'default'),
                ];
            })->all(),
        );

        return self::SUCCESS;
    }
}
