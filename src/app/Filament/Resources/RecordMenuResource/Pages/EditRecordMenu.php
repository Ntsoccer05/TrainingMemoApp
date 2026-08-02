<?php

namespace App\Filament\Resources\RecordMenuResource\Pages;

use App\Filament\Resources\RecordMenuResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecordMenu extends EditRecord
{
    protected static string $resource = RecordMenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
