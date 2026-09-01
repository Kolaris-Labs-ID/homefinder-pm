<?php

namespace App\Support;

/**
 * Prevents CSV/Excel formula injection: a cell that starts with one of these
 * characters is treated as a formula by spreadsheet apps, so prefix it with a
 * single quote to force text. Apply to every user-controlled exported cell
 * (names, titles, free text, filenames, parsed summaries, criteria explanations).
 */
class CsvFormulaGuard
{
    private const TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function escape(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (in_array($value[0], self::TRIGGERS, true)) {
            return "'".$value;
        }

        return $value;
    }

    // .xlsx only: PhpSpreadsheet's DefaultValueBinder promotes a string to a formula
    // solely on a leading '=', so applying the CSV triggers here would prefix an
    // apostrophe onto ordinary text such as "- fix login redirect".
    public static function escapeXlsx(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return $value[0] === '=' ? "'".$value : $value;
    }
}
