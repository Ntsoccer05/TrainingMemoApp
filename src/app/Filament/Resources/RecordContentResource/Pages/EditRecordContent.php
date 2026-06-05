<?php

namespace App\Filament\Resources\RecordContentResource\Pages;

use App\Filament\Resources\RecordContentResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecordContent extends EditRecord
{
    protected static string $resource = RecordContentResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
