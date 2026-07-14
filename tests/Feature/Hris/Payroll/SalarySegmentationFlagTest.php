<?php

use App\Models\Hris\PayrollSetting;
use App\Services\Hris\PayrollSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function segFlag(): bool
{
    return app(PayrollSettingService::class)->isSalarySegmentationEnabled();
}

it('falls back to the config default when no DB toggle row exists', function () {
    config()->set('payroll.effective_date_salary_segmentation', true);
    expect(segFlag())->toBeTrue();

    config()->set('payroll.effective_date_salary_segmentation', false);
    expect(segFlag())->toBeFalse();
});

it('lets the DB toggle override the config default (both directions)', function () {
    config()->set('payroll.effective_date_salary_segmentation', false);
    $row = PayrollSetting::create([
        'key' => 'salary_effective_date_segmentation',
        'value' => ['enabled' => true],
        'category' => 'general',
        'label' => 'Effective-date salary segmentation',
    ]);
    expect(segFlag())->toBeTrue(); // DB on beats config off

    $row->update(['value' => ['enabled' => false]]);
    config()->set('payroll.effective_date_salary_segmentation', true);
    expect(segFlag())->toBeFalse(); // DB off beats config on
});
