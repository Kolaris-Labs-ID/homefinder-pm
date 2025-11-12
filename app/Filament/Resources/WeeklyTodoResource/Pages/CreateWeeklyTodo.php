<?php

namespace App\Filament\Resources\WeeklyTodoResource\Pages;

use App\Filament\Resources\WeeklyTodoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Carbon\Carbon;

class CreateWeeklyTodo extends CreateRecord
{
    protected static string $resource = WeeklyTodoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        
        // Always ensure user_id is set
        // For regular users (non-management), always set to their own ID
        if (!self::isManagementRole($user)) {
            $data['user_id'] = $user->id;
        } elseif (!isset($data['user_id']) || empty($data['user_id'])) {
            // For management roles, use selected user_id or default to current user
            $data['user_id'] = $data['user_id'] ?? $user->id;
        }
        
        // Ensure week_start_date is set to start of week (Monday)
        if (isset($data['week_start_date'])) {
            $data['week_start_date'] = Carbon::parse($data['week_start_date'])->startOfWeek(Carbon::MONDAY);
        }
        
        return $data;
    }

    /**
     * Check if user is in management role
     */
    private static function isManagementRole($user): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->hasRole(['super_admin', 'admin', 'CEO', 'COO', 'CTO', 'HR']) 
            || ($user->is_division_head && $user->division);
    }

    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
    {
        return \Filament\Notifications\Notification::make()
            ->success()
            ->title('Weekly To-Do Created')
            ->body('Your weekly to-do has been created successfully.');
    }
}

