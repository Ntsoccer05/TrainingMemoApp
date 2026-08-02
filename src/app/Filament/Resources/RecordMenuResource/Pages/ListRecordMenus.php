<?php

namespace App\Filament\Resources\RecordMenuResource\Pages;

use App\Filament\Resources\RecordMenuResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecordMenus extends ListRecords
{
    protected static string $resource = RecordMenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
