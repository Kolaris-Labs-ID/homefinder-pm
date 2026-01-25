<?php

use App\Models\Hris\PayrollSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PayrollSetting Model', function () {
    it('can create payroll setting with json value', function () {
        $setting = PayrollSetting::create([
            'key' => 'bpjs_kes_employee_rate',
            'value' => ['rate' => 0.01],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'BPJS Kesehatan Employee Rate',
        ]);

        expect($setting->id)->not->toBeNull()
            ->and($setting->key)->toBe('bpjs_kes_employee_rate')
            ->and($setting->value)->toBe(['rate' => 0.01])
            ->and($setting->category)->toBe('bpjs');
    });

    it('casts value to array', function () {
        $setting = PayrollSetting::create([
            'key' => 'test_setting',
            'value' => ['amount' => 12000000, 'currency' => 'IDR'],
            'category' => PayrollSetting::CATEGORY_GENERAL,
            'label' => 'Test Setting',
        ]);

        expect($setting->value)->toBeArray()
            ->and($setting->value['amount'])->toBe(12000000)
            ->and($setting->value['currency'])->toBe('IDR');
    });

    it('enforces unique key constraint', function () {
        PayrollSetting::create([
            'key' => 'unique_key',
            'value' => ['test' => true],
            'category' => PayrollSetting::CATEGORY_GENERAL,
            'label' => 'Unique Setting',
        ]);

        expect(fn () => PayrollSetting::create([
            'key' => 'unique_key',
            'value' => ['test' => false],
            'category' => PayrollSetting::CATEGORY_GENERAL,
            'label' => 'Duplicate Setting',
        ]))->toThrow(Exception::class);
    });
});

describe('PayrollSetting::getValue', function () {
    it('returns value when key exists', function () {
        PayrollSetting::create([
            'key' => 'bpjs_rate',
            'value' => ['rate' => 0.02, 'ceiling' => 12000000],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'BPJS Rate',
        ]);

        $value = PayrollSetting::getValue('bpjs_rate');

        expect($value)->toBeArray()
            ->and($value['rate'])->toBe(0.02)
            ->and($value['ceiling'])->toBe(12000000);
    });

    it('returns default when key not found', function () {
        $value = PayrollSetting::getValue('nonexistent_key', ['default' => 'value']);

        expect($value)->toBe(['default' => 'value']);
    });

    it('returns null when key not found and no default provided', function () {
        $value = PayrollSetting::getValue('nonexistent_key');

        expect($value)->toBeNull();
    });
});

describe('PayrollSetting::getValueField', function () {
    it('returns specific field from value', function () {
        PayrollSetting::create([
            'key' => 'bpjs_kes_ceiling',
            'value' => ['amount' => 12000000],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'BPJS Kesehatan Ceiling',
        ]);

        $amount = PayrollSetting::getValueField('bpjs_kes_ceiling', 'amount');

        expect($amount)->toBe(12000000);
    });

    it('returns default when field not found', function () {
        PayrollSetting::create([
            'key' => 'test_setting',
            'value' => ['other_field' => 123],
            'category' => PayrollSetting::CATEGORY_GENERAL,
            'label' => 'Test',
        ]);

        $value = PayrollSetting::getValueField('test_setting', 'missing_field', 0.05);

        expect($value)->toBe(0.05);
    });

    it('returns default when key not found', function () {
        $value = PayrollSetting::getValueField('nonexistent', 'rate', 0.01);

        expect($value)->toBe(0.01);
    });
});

describe('PayrollSetting::setValue', function () {
    it('creates new setting', function () {
        $setting = PayrollSetting::setValue(
            'new_setting',
            ['value' => 100],
            PayrollSetting::CATEGORY_GENERAL,
            'New Setting',
            'A new setting description'
        );

        expect($setting->key)->toBe('new_setting')
            ->and($setting->value)->toBe(['value' => 100])
            ->and($setting->description)->toBe('A new setting description');
    });

    it('updates existing setting', function () {
        PayrollSetting::create([
            'key' => 'existing_setting',
            'value' => ['old' => true],
            'category' => PayrollSetting::CATEGORY_GENERAL,
            'label' => 'Existing',
        ]);

        $setting = PayrollSetting::setValue(
            'existing_setting',
            ['new' => true],
            PayrollSetting::CATEGORY_BPJS,
            'Updated Setting'
        );

        expect($setting->value)->toBe(['new' => true])
            ->and($setting->category)->toBe(PayrollSetting::CATEGORY_BPJS)
            ->and(PayrollSetting::where('key', 'existing_setting')->count())->toBe(1);
    });
});

describe('PayrollSetting Scopes', function () {
    beforeEach(function () {
        PayrollSetting::create([
            'key' => 'bpjs_1',
            'value' => [],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'BPJS 1',
        ]);
        PayrollSetting::create([
            'key' => 'tax_1',
            'value' => [],
            'category' => PayrollSetting::CATEGORY_TAX,
            'label' => 'Tax 1',
        ]);
        PayrollSetting::create([
            'key' => 'general_1',
            'value' => [],
            'category' => PayrollSetting::CATEGORY_GENERAL,
            'label' => 'General 1',
        ]);
    });

    it('filters by category using forCategory scope', function () {
        $bpjsSettings = PayrollSetting::forCategory(PayrollSetting::CATEGORY_BPJS)->get();

        expect($bpjsSettings)->toHaveCount(1)
            ->and($bpjsSettings->first()->key)->toBe('bpjs_1');
    });

    it('filters BPJS settings using bpjs scope', function () {
        $settings = PayrollSetting::bpjs()->get();

        expect($settings)->toHaveCount(1)
            ->and($settings->first()->category)->toBe('bpjs');
    });

    it('filters tax settings using tax scope', function () {
        $settings = PayrollSetting::tax()->get();

        expect($settings)->toHaveCount(1)
            ->and($settings->first()->category)->toBe('tax');
    });

    it('filters general settings using general scope', function () {
        $settings = PayrollSetting::general()->get();

        expect($settings)->toHaveCount(1)
            ->and($settings->first()->category)->toBe('general');
    });
});

describe('PayrollSetting Category Helpers', function () {
    it('returns all categories', function () {
        $categories = PayrollSetting::getCategories();

        expect($categories)->toContain('bpjs', 'tax', 'general');
    });

    it('returns category labels', function () {
        $labels = PayrollSetting::getCategoryLabels();

        expect($labels)->toHaveKeys(['bpjs', 'tax', 'general'])
            ->and($labels['bpjs'])->toContain('BPJS');
    });
});
