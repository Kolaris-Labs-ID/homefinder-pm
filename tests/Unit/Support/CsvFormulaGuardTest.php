<?php

use App\Support\CsvFormulaGuard;

it('prefixes a quote before formula-trigger leading characters', function () {
    expect(CsvFormulaGuard::escape('=SUM(A1)'))->toBe("'=SUM(A1)")
        ->and(CsvFormulaGuard::escape('+1'))->toBe("'+1")
        ->and(CsvFormulaGuard::escape('-1'))->toBe("'-1")
        ->and(CsvFormulaGuard::escape('@cmd'))->toBe("'@cmd")
        ->and(CsvFormulaGuard::escape("\tx"))->toBe("'\tx")
        ->and(CsvFormulaGuard::escape("\rx"))->toBe("'\rx");
});

it('leaves safe values, null and empty unchanged', function () {
    expect(CsvFormulaGuard::escape('Backend Engineer'))->toBe('Backend Engineer')
        ->and(CsvFormulaGuard::escape(null))->toBeNull()
        ->and(CsvFormulaGuard::escape(''))->toBe('');
});
