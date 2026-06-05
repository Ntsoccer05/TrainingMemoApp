<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecordStateResource\Pages;
use App\Filament\Resources\RecordStateResource\RelationManagers;
use App\Models\RecordState;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecordStateResource extends Resource
{
    protected static ?string $model = RecordState::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'id')
                    ->required(),
                Forms\Components\TextInput::make('bodyWeight'),
                Forms\Components\DatePicker::make('recorded_at')->displayFormat('Y年m月d日')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('bodyWeight'),
                Tables\Columns\TextColumn::make('recorded_at')
                    ->date('Y年Md日')->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y年Md日 h:m:s'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('Y年Md日 h:m:s')->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecordStates::route('/'),
            'create' => Pages\CreateRecordState::route('/create'),
            'edit' => Pages\EditRecordState::route('/{record}/edit'),
        ];
    }    
}
