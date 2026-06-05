<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecordContentResource\Pages;
use App\Filament\Resources\RecordContentResource\RelationManagers;
use App\Models\RecordContent;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecordContentResource extends Resource
{
    protected static ?string $model = RecordContent::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'id')
                    ->required(),
                Forms\Components\Select::make('record_state_id')
                    ->relationship('recordState', 'id'),
                Forms\Components\Select::make('record_menu_id')
                    ->relationship('recordMenu', 'id'),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'id'),
                Forms\Components\Select::make('menu_id')
                    ->relationship('menu', 'id'),
                Forms\Components\TextInput::make('weight'),
                Forms\Components\TextInput::make('right_weight'),
                Forms\Components\TextInput::make('right_rep'),
                Forms\Components\TextInput::make('left_weight'),
                Forms\Components\TextInput::make('left_rep'),
                Forms\Components\TextInput::make('volume'),
                Forms\Components\TextInput::make('right_volume'),
                Forms\Components\TextInput::make('left_volume'),
                Forms\Components\TextInput::make('set'),
                Forms\Components\TextInput::make('rep'),
                Forms\Components\TextInput::make('memo')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('recordState.id')->sortable(),
                Tables\Columns\TextColumn::make('recordMenu.id'),
                Tables\Columns\TextColumn::make('category.id'),
                Tables\Columns\TextColumn::make('menu.id'),
                Tables\Columns\TextColumn::make('weight')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('right_weight')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('right_rep'),
                Tables\Columns\TextColumn::make('left_weight')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('left_rep'),
                Tables\Columns\TextColumn::make('volume')->sortable(),
                Tables\Columns\TextColumn::make('right_volume')->sortable(),
                Tables\Columns\TextColumn::make('left_volume')->sortable(),
                Tables\Columns\TextColumn::make('set'),
                Tables\Columns\TextColumn::make('rep'),
                Tables\Columns\TextColumn::make('memo')->sortable(),
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
            'index' => Pages\ListRecordContents::route('/'),
            'create' => Pages\CreateRecordContent::route('/create'),
            'edit' => Pages\EditRecordContent::route('/{record}/edit'),
        ];
    }    
}
