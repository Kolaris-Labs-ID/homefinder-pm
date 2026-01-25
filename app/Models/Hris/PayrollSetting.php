<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    use HasFactory;

    // Category constants
    const CATEGORY_BPJS = 'bpjs';
    const CATEGORY_TAX = 'tax';
    const CATEGORY_GENERAL = 'general';

    protected $fillable = [
        'key',
        'value',
        'category',
        'label',
        'description',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Get all available categories.
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_BPJS,
            self::CATEGORY_TAX,
            self::CATEGORY_GENERAL,
        ];
    }

    /**
     * Get category labels for display.
     */
    public static function getCategoryLabels(): array
    {
        return [
            self::CATEGORY_BPJS => 'BPJS (Social Security)',
            self::CATEGORY_TAX => 'Tax (PPh 21)',
            self::CATEGORY_GENERAL => 'General Settings',
        ];
    }

    /**
     * Get a setting value by key.
     *
     * @param string $key The setting key
     * @param mixed $default Default value if not found
     * @return mixed The setting value or default
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Get a specific field from a setting value.
     *
     * @param string $key The setting key
     * @param string $field The field within the JSON value
     * @param mixed $default Default value if not found
     * @return mixed The field value or default
     */
    public static function getValueField(string $key, string $field, $default = null)
    {
        $value = static::getValue($key);

        if ($value === null || !is_array($value)) {
            return $default;
        }

        return $value[$field] ?? $default;
    }

    /**
     * Set a setting value by key.
     *
     * @param string $key The setting key
     * @param mixed $value The value to set
     * @param string $category The category
     * @param string $label The display label
     * @param string|null $description Optional description
     * @return static
     */
    public static function setValue(
        string $key,
        $value,
        string $category = self::CATEGORY_GENERAL,
        string $label = '',
        ?string $description = null
    ): static {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'category' => $category,
                'label' => $label ?: $key,
                'description' => $description,
            ]
        );
    }

    /**
     * Scope to filter by category.
     */
    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get BPJS settings.
     */
    public function scopeBpjs(Builder $query): Builder
    {
        return $query->where('category', self::CATEGORY_BPJS);
    }

    /**
     * Scope to get tax settings.
     */
    public function scopeTax(Builder $query): Builder
    {
        return $query->where('category', self::CATEGORY_TAX);
    }

    /**
     * Scope to get general settings.
     */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->where('category', self::CATEGORY_GENERAL);
    }
}
