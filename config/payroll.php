<?php

return [
    // F1: effective-date salary segmentation. Production default ON; a DB toggle
    // (payroll_settings key "salary_effective_date_segmentation") overrides this at
    // runtime, and the test suite pins it OFF via phpunit.xml.
    'effective_date_salary_segmentation' => env('PAYROLL_EFFECTIVE_DATE_SALARY_SEGMENTATION', true),
];
