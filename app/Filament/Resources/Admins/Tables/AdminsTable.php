<?php

namespace App\Filament\Resources\Admins\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn ($record): ?string => $record->getKey() === Filament::auth()->id()
                        ? 'This is you'
                        : null),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->date('j M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            // No bulk delete: the per-record guard in AdminResource::canDelete()
            // protects the current operator and the last account, and a bulk path
            // is an easy way to route around both at once.
            ->toolbarActions([]);
    }
}
