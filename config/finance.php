<?php

return [
    /*
     * Payment bundles at or above this IDR amount are "high value": the bundle creator
     * may not also be the executive signer (segregation of duties). String for bcmath.
     */
    'payment_bundle_high_value_threshold' => env('PAYMENT_BUNDLE_HIGH_VALUE_THRESHOLD', '10000000'),
];
