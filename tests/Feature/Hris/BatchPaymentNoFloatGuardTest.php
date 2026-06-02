<?php

it('does not use float casts or floatval in new batch payment services and snapshot support', function () {
    $paths = [
        app_path('Services/Hris/BatchPaymentBundleService.php'),
        ...glob(app_path('Services/Hris/BatchPayments/*.php')),
        app_path('Support/Finance/PaymentBundleSnapshot.php'),
        app_path('Support/Finance/BatchPaymentSnapshotCollection.php'),
        app_path('Support/Finance/MoneyString.php'),
    ];

    foreach ($paths as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->not->toContain('(float)')
            ->not->toContain('floatval');
    }
});
