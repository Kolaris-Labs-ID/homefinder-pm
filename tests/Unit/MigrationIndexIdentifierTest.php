<?php

use Illuminate\Support\Str;

it('keeps generated MySQL migration index names within the identifier limit', function () {
    $violations = [];

    foreach (glob(database_path('migrations/*.php')) as $migration) {
        $contents = file_get_contents($migration);

        preg_match_all(
            '/Schema::(?:create|table)\(\s*\'([^\']+)\'\s*,\s*function\s*\([^)]*\)\s*\{(.*?)^\s*\}\);/ms',
            $contents,
            $tableBlocks,
            PREG_SET_ORDER
        );

        foreach ($tableBlocks as [, $tableName, $block]) {
            preg_match_all(
                '/\\$table->(unique|index)\(\s*\[([^\]]+)\]\s*\)/s',
                $block,
                $indexDefinitions,
                PREG_SET_ORDER
            );

            foreach ($indexDefinitions as [, $indexType, $columnList]) {
                preg_match_all('/\'([^\']+)\'/', $columnList, $columnMatches);

                $columns = $columnMatches[1];
                $generatedName = Str::of(implode('_', array_merge([$tableName], $columns, [$indexType])))
                    ->lower()
                    ->replace(['-', '.'], '_')
                    ->toString();

                if (strlen($generatedName) > 64) {
                    $violations[] = sprintf(
                        '%s defines %s (%d chars)',
                        basename($migration),
                        $generatedName,
                        strlen($generatedName)
                    );
                }
            }
        }
    }

    test()->assertEmpty($violations, implode(PHP_EOL, $violations));
});
