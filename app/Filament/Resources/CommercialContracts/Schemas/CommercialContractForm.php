<?php

namespace App\Filament\Resources\CommercialContracts\Schemas;

use App\Models\School;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CommercialContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entity Selection')
                    ->description('Select the Entity that this contract will be effective on. Select no School if applicable to all.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('legal_entity_id')
                            ->relationship('legalEntity', 'legal_name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (? int $state, Get $get, Set $set): void {
                                $schoolId = $get('school_id');

                                if ($schoolId && $state && ! School::whereKey($schoolId)->where('legal_entity_id', $state)->exists()) {
                                    $set('school_id', null);
                                }
                            })
                            ->required(),
                        Select::make('school_id')
                            ->relationship(
                                name: 'school',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->when(
                                    $get('legal_entity_id'),
                                    fn (Builder $q, int $legalEntityId): Builder => $q->where('legal_entity_id', $legalEntityId),
                                ),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (?int $state, Set $set): void {
                                if ($state) {
                                    $set('legal_entity_id', School::whereKey($state)->value('legal_entity_id'));
                                }
                            }),
                    ]),
                TextInput::make('reference_code'),
                Select::make('status')
                    ->required()
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'terminated' => 'Terminated',
                        'expired' => 'Expired',
                        'revoked' => 'Revoked',
                    ])
                    ->default('draft'),
                DatePicker::make('effective_from'),
                DatePicker::make('effective_to'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
