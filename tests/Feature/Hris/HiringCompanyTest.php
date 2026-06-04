<?php

use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;

it('auto-generates a kebab-case slug from the name', function () {
    $company = HiringCompany::create(['name' => 'Acme Digital Studio']);

    expect($company->slug)->toBe('acme-digital-studio');
});

it('keeps slugs unique when names collide', function () {
    $a = HiringCompany::create(['name' => 'Acme Studio']);
    $b = HiringCompany::create(['name' => 'Acme Studio']);

    expect($a->slug)->toBe('acme-studio');
    expect($b->slug)->not->toBe($a->slug);
    expect($b->slug)->toStartWith('acme-studio-');
});

it('respects an explicitly provided slug', function () {
    $company = HiringCompany::create(['name' => 'Acme', 'slug' => 'custom-slug']);

    expect($company->slug)->toBe('custom-slug');
});

it('enforces slug uniqueness at the database level', function () {
    HiringCompany::create(['name' => 'One', 'slug' => 'dupe']);
    HiringCompany::create(['name' => 'Two', 'slug' => 'dupe']);
})->throws(Illuminate\Database\QueryException::class);

it('defaults to active status and external company', function () {
    $company = HiringCompany::create(['name' => 'Acme']);

    expect($company->status)->toBe(HiringCompany::STATUS_ACTIVE);
    expect($company->is_internal)->toBeFalse();
});

it('scopes active companies only', function () {
    HiringCompany::create(['name' => 'Active Co']);
    HiringCompany::create(['name' => 'Inactive Co', 'status' => HiringCompany::STATUS_INACTIVE]);

    expect(HiringCompany::active()->count())->toBe(1);
    expect(HiringCompany::active()->first()->name)->toBe('Active Co');
});

it('allows slug edits before any public job is published', function () {
    $company = HiringCompany::create(['name' => 'Acme']);
    $company->update(['slug' => 'acme-renamed']);

    expect($company->fresh()->slug)->toBe('acme-renamed');
});

it('locks the slug once a public job is published', function () {
    $company = HiringCompany::create(['name' => 'Acme']);
    JobPosting::factory()->publicPortal()->create(['hiring_company_id' => $company->id]);

    $company->update(['slug' => 'acme-renamed']);
})->throws(RuntimeException::class);
