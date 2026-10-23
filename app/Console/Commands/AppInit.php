<?php

namespace App\Console\Commands;

use App\Features\Feature;
use App\Features\FeatureManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * First setup of a company's feature flags. Safe to run on every deploy: it only
 * stores flags that have no value yet and never touches one that is set.
 */
class AppInit extends Command
{
    protected $signature = 'app:init
        {--preset= : Company preset from config/features.php (defaults to APP_FEATURE_PRESET)}';

    protected $description = 'Choose and lock the company feature flags that are not set yet';

    public function handle(FeatureManager $features): int
    {
        if (! Schema::hasTable('app_features')) {
            $this->error('The app_features table does not exist. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $presetName = $this->option('preset') ?: $features->presetName();
        $preset = [];

        if ($presetName !== null) {
            $preset = $features->preset($presetName);

            if ($preset === null) {
                $this->error("Unknown preset [{$presetName}]. Presets: ".implode(', ', array_keys(config('features.presets'))).'.');

                return self::FAILURE;
            }
        }

        $pending = $features->pending();

        if ($pending === []) {
            $this->info('Every company feature is already set.');
            $this->call('features:status');

            return self::SUCCESS;
        }

        $choices = [];

        foreach ($pending as $feature) {
            if (array_key_exists($feature->value, $preset)) {
                $choices[$feature->value] = (bool) $preset[$feature->value];
            } elseif ($this->input->isInteractive()) {
                $choices[$feature->value] = $this->confirm(
                    "{$feature->label()}: {$feature->description()} Turn it on?",
                    $feature->default(),
                );
            }
        }

        if ($choices !== []) {
            $this->table(['Feature', 'Choice'], collect($choices)->map(
                fn (bool $on, string $key) => [Feature::from($key)->label(), $on ? 'on' : 'off']
            )->values()->all());

            if ($this->input->isInteractive()
                && ! $this->confirm('Lock these choices? Changing one later needs features:change and a reason.', true)) {
                $this->warn('Nothing stored.');

                return self::SUCCESS;
            }

            $source = $presetName !== null ? "preset:{$presetName}" : 'cli';
            $written = $features->initialize($choices, $source);

            $this->info('Stored '.count($written).' feature '.str('choice')->plural(count($written)).'.');
        }

        $left = $features->pending();

        if ($left !== []) {
            // Not a failure: a deploy runs this without a terminal, and a new company
            // may choose on the Company Features page instead.
            $this->warn('Not set yet: '.collect($left)->map->label()->join(', ')
                .'. Choose them on the Company Features page or run app:init in a terminal.');
        }

        return self::SUCCESS;
    }
}
