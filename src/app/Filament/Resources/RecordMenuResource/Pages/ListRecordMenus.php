<?php

namespace App\Filament\Resources\RecordMenuResource\Pages;

use App\Filament\Resources\RecordMenuResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecordMenus extends ListRecords
{
    protected static string $resource = RecordMenuResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
