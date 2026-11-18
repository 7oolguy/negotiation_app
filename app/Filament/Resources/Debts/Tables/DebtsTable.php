<?php

namespace App\Filament\Resources\Debts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DebtsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('school.name')
                    ->searchable(),
                TextColumn::make('student.name')
                    ->searchable(),
                TextColumn::make('guardian.name')
                    ->searchable(),
                TextColumn::make('enrollmentContract.id')
                    ->searchable(),
                TextColumn::make('external_reference')
                    ->searchable(),
                TextColumn::make('principal_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('interest_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('fine_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('correction_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                IconColumn::make('is_disputed')
                    ->boolean(),
                IconColumn::make('is_suspended')
                    ->boolean(),
                IconColumn::make('under_payment_verification')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
