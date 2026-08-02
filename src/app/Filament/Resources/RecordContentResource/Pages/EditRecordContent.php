<?php

namespace App\Filament\Resources\RecordContentResource\Pages;

use App\Filament\Resources\RecordContentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecordContent extends EditRecord
{
    protected static string $resource = RecordContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
