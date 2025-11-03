<?php

namespace App\Filament\Resources\DailyTodoResource\Pages;

use App\Filament\Resources\DailyTodoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDailyTodo extends CreateRecord
{
    protected static string $resource = DailyTodoResource::class;

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
            ->title('Daily To-Do Created')
            ->body('Your daily to-do has been created successfully.');
    }
}
