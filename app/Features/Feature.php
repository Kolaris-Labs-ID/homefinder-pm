<?php

namespace App\Features;

/**
 * Behaviour that differs between the companies running this codebase.
 *
 * Each company chooses every flag once, at first setup (php artisan app:init or
 * the Company Features page), and the choice is then locked. A flag is for a
 * difference in company policy; bug fixes are never flagged.
 *
 * Adding a case: give it a label, a description and a default equal to the
 * behaviour the code had before the flag existed, add it to every preset in
 * config/features.php, and gate the code paths it controls.
 */
enum Feature: string
{
    case Recruitment = 'module.recruitment';
    case GoogleLogin = 'auth.google_login';
    case OvertimeStatutoryCaps = 'payroll.overtime_statutory_caps';

    public function label(): string
    {
        return match ($this) {
            self::Recruitment => 'Recruitment',
            self::GoogleLogin => 'Google sign-in',
            self::OvertimeStatutoryCaps => 'Overtime statutory caps',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Recruitment => 'Job postings, applicants, interviews, résumé screening and the public job-portal API.',
            self::GoogleLogin => 'Sign in with a Google account on the admin panel and the mobile app.',
            self::OvertimeStatutoryCaps => 'Pay weekday overtime up to 4 hours a day and 18 hours a week (PP 35/2021 art. 26). Off pays every approved minute.',
        };
    }

    /** The behaviour before this flag existed, used until the company has chosen. */
    public function default(): bool
    {
        return match ($this) {
            self::Recruitment, self::GoogleLogin, self::OvertimeStatutoryCaps => true,
        };
    }
}
