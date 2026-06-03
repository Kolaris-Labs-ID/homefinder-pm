<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * ER / PR Workflow Guide — read-only reference page for the Finance team.
 *
 * Renders the full Expense Request → Payment Request → Payment Bundle
 * workflow documentation inside Finance Center so that finance staff
 * can quickly look up statuses, rules, and signing procedures without
 * leaving the admin panel.
 */
class ErPrWorkflowGuide extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'ER/PR Workflow Guide';

    protected static ?string $title = 'Alur Kerja ER / PR';

    protected static ?string $navigationGroup = 'Finance Center';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'finance-center/er-pr-workflow-guide';

    protected static string $view = 'filament.pages.er-pr-workflow-guide';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        try {
            return $user->hasPermissionTo('finance_center.view', 'web');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
