<?php

namespace App\Filament\Resources\RecordStateResource\Pages;

use App\Filament\Resources\RecordStateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecordState extends EditRecord
{
    protected static string $resource = RecordStateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
