<?php

namespace App\Filament\Resources\TicketCommentResource\Pages;

use App\Filament\Resources\TicketCommentResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTicketComments extends ListRecords
{
    protected static string $resource = TicketCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(): void
    {
        redirect()->to(route('filament.admin.pages.dashboard'));
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->whereRaw('1=0');
    }
}
