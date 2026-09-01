<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Actions\ExportTicketsAction;
use App\Filament\Resources\TicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ExportTicketsAction::make()
                ->visible(fn () => TicketResource::canViewAny()),
        ];
    }
}
