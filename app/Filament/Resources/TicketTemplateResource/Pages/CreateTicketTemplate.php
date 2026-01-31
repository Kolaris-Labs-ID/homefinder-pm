<?php

namespace App\Filament\Resources\TicketTemplateResource\Pages;

use App\Filament\Resources\TicketTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketTemplate extends CreateRecord
{
    protected static string $resource = TicketTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
