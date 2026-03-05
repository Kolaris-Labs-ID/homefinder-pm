<?php

namespace App\Filament\Resources\SalaryStructureResource\Pages;

use App\Filament\Resources\SalaryStructureResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSalaryStructure extends EditRecord
{
    protected static string $resource = SalaryStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validate 75:25 rule before saving
        $basicSalary = (float) ($data['basic_salary'] ?? 0);
        $fixedAllowances = collect($data['allowances_fixed'] ?? [])->sum('amount');
        $basicPlusFixed = $basicSalary + $fixedAllowances;

        if ($basicPlusFixed > 0 && ($basicSalary / $basicPlusFixed) < 0.75) {
            Notification::make()
                ->title('Validation Error')
                ->body(
                    'Basic salary must be at least 75% of (basic salary + fixed allowances). ' .
                    'Current ratio: ' . number_format(($basicSalary / $basicPlusFixed) * 100, 1) . '%'
                )
                ->danger()
                ->send();

            $this->halt();
        }

        return $data;
    }
}
