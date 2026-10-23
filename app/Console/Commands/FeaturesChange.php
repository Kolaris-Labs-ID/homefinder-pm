<?php

namespace App\Console\Commands;

use App\Features\Feature;
use App\Features\FeatureManager;
use Illuminate\Console\Command;

class FeaturesChange extends Command
{
    protected $signature = 'features:change
        {feature : Feature key, e.g. module.recruitment}
        {value : on or off}
        {--reason= : Why the company policy changed (required, kept in app_feature_changes)}';

    protected $description = 'Change a company feature flag after first setup (audited)';

    public function handle(FeatureManager $features): int
    {
        $feature = Feature::tryFrom($this->argument('feature'));

        if ($feature === null) {
            $this->error('Unknown feature. Keys: '.collect(Feature::cases())->map->value->join(', ').'.');

            return self::FAILURE;
        }

        $value = match (strtolower($this->argument('value'))) {
            'on', 'true', '1' => true,
            'off', 'false', '0' => false,
            default => null,
        };

        if ($value === null) {
            $this->error('Value must be on or off.');

            return self::FAILURE;
        }

        $reason = (string) $this->option('reason');

        if (trim($reason) === '') {
            $this->error('--reason is required.');

            return self::FAILURE;
        }

        if (! $features->isInitialized($feature)) {
            $this->error("{$feature->label()} has not been set yet. Run app:init first.");

            return self::FAILURE;
        }

        $current = $features->active($feature);

        if ($current === $value) {
            $this->info("{$feature->label()} is already ".($value ? 'on' : 'off').'.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Turn {$feature->label()} ".($value ? 'on' : 'off').' for this company?', false)) {
            $this->warn('Nothing changed.');

            return self::SUCCESS;
        }

        $features->change($feature, $value, $reason, 'cli');

        $this->info("{$feature->label()} is now ".($value ? 'on' : 'off').'.');

        return self::SUCCESS;
    }
}
