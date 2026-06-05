<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecordMenuResource\Pages;
use App\Filament\Resources\RecordMenuResource\RelationManagers;
use App\Models\RecordMenu;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecordMenuResource extends Resource
{
    protected static ?string $model = RecordMenu::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'id')
                    ->required(),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'id'),
                Forms\Components\Select::make('menu_id')
                    ->relationship('menu', 'id'),
                Forms\Components\Select::make('record_state_id')
                    ->relationship('recordState', 'id'),
                Forms\Components\DatePicker::make('recorded_at')->displayFormat('Y年m月d日')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('category.id'),
                Tables\Columns\TextColumn::make('menu.id'),
                Tables\Columns\TextColumn::make('recordState.id'),
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
            'index' => Pages\ListRecordMenus::route('/'),
            'create' => Pages\CreateRecordMenu::route('/create'),
            'edit' => Pages\EditRecordMenu::route('/{record}/edit'),
        ];
    }    
}
