<?php

namespace App\Features;

use App\Models\AppFeature;
use App\Models\AppFeatureChange;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Resolves a company's feature flags.
 *
 * A flag's value is, in order: the value stored at first setup, the value in the
 * deployment's preset (APP_FEATURE_PRESET), then the flag's default. Once stored,
 * a value never changes except through change(), which needs a reason and is
 * written to app_feature_changes.
 *
 * Bound as a scoped singleton, so under Octane each request reads the table once.
 */
class FeatureManager
{
    /** @var array<string, AppFeature>|null */
    private ?array $stored = null;

    public function active(Feature $feature): bool
    {
        return (bool) $this->value($feature);
    }

    public function value(Feature $feature): mixed
    {
        $stored = $this->stored();

        if (isset($stored[$feature->value])) {
            return $stored[$feature->value]->value;
        }

        return $this->presetValue($feature) ?? $feature->default();
    }

    public function isInitialized(Feature $feature): bool
    {
        return isset($this->stored()[$feature->value]);
    }

    public function record(Feature $feature): ?AppFeature
    {
        return $this->stored()[$feature->value] ?? null;
    }

    /** @return list<Feature> */
    public function pending(): array
    {
        return array_values(array_filter(Feature::cases(), fn (Feature $f) => ! $this->isInitialized($f)));
    }

    /**
     * Store the first choice for each flag that has none yet. Flags already set are
     * left alone, and a concurrent setup cannot overwrite them (first write wins).
     *
     * @param  array<string, bool>  $values  keyed by Feature value
     * @return list<Feature> the flags this call stored
     */
    public function initialize(array $values, string $source): array
    {
        $written = [];

        foreach ($values as $key => $value) {
            $feature = Feature::tryFrom($key) ?? throw new InvalidArgumentException("Unknown feature [{$key}].");

            if ($this->isInitialized($feature)) {
                continue;
            }

            $inserted = AppFeature::query()->insertOrIgnore([
                'key' => $feature->value,
                'value' => json_encode((bool) $value),
                'source' => $source,
                'initialized_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted) {
                $written[] = $feature;
            }
        }

        $this->flush();

        return $written;
    }

    /**
     * Change a locked flag. Meant for the rare case where a company's policy really
     * changed; callers must make sure the switch happens at a safe point (for a
     * payroll flag, between pay periods).
     */
    public function change(Feature $feature, bool $value, string $reason, string $actor): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to change a locked feature.');
        }

        $record = $this->record($feature)
            ?? throw new LogicException("Feature [{$feature->value}] has not been set yet. Set it with app:init first.");

        DB::transaction(function () use ($feature, $record, $value, $reason, $actor) {
            AppFeatureChange::create([
                'key' => $feature->value,
                'old_value' => $record->value,
                'new_value' => $value,
                'reason' => $reason,
                'actor' => $actor,
            ]);

            $record->forceFill(['value' => $value])->save();
        });

        $this->flush();
    }

    /** @return array<string, bool> every flag's effective value, keyed by Feature value */
    public function all(): array
    {
        $values = [];

        foreach (Feature::cases() as $feature) {
            $values[$feature->value] = $this->active($feature);
        }

        return $values;
    }

    public function presetName(): ?string
    {
        $preset = config('features.preset');

        return filled($preset) ? (string) $preset : null;
    }

    /** @return array<string, bool>|null */
    public function preset(string $name): ?array
    {
        return config("features.presets.{$name}");
    }

    public function flush(): void
    {
        $this->stored = null;
    }

    private function presetValue(Feature $feature): ?bool
    {
        $name = $this->presetName();

        if ($name === null) {
            return null;
        }

        $value = $this->preset($name)[$feature->value] ?? null;

        return $value === null ? null : (bool) $value;
    }

    /** @return array<string, AppFeature> */
    private function stored(): array
    {
        if ($this->stored !== null) {
            return $this->stored;
        }

        try {
            return $this->stored = AppFeature::query()->get()->keyBy('key')->all();
        } catch (QueryException) {
            // Table not migrated yet (fresh install, or mid-deploy): fall back to
            // preset and defaults, and don't remember the miss.
            return [];
        }
    }
}
