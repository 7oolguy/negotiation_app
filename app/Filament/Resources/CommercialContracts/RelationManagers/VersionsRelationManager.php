<?php

namespace App\Filament\Resources\CommercialContracts\RelationManagers;

use App\Models\CommercialContract;
use App\Models\ContractVersion;
use App\Services\ContractVersioningService;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Laravel\Prompts\Key;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('effective_from'),
                DatePicker::make('effective_to'),
                Select::make('rate_type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed',
                        'tiered' => 'Tiered',
                    ]),
                TextInput::make('rate_bps')
                    ->label('Rate (basis points, 100 = 1%)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('fixed_fee_cents')
                    ->label('Fixed fee (centavos)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('attibution_window_days')
                    ->numeric()
                    ->minValue(0),
                Select::make('psp_fee_treatment')
                    ->options([
                        'deduct_from_school' => 'Deduct from School',
                        'deduct_from_commission' => 'Deduct from Commission',
                        'absorb_by_system' => 'Absorb by System',
                        'split' => 'Split',
                    ]),
                TextInput::make('billing_closing_days')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31),
                CheckboxList::make('eligible_components')
                    ->options([
                        'principal' => 'Principal',
                        'interest' => 'Interest',
                        'fine' => 'Fine',
                        'correction' => 'Monetary Correction',
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                KeyValue::make('rate_tiers')
                    ->columnSpanFull(),
                KeyValue::make('approval_rules')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version_number')
            ->defaultSort('version_number', 'desc')
            ->columns([
                TextColumn::make('version_number')
                    ->label('Version')
                    ->prefix('v')
                    ->sortable(),
                TextColumn::make('rate_type')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('rate_bps')
                    ->label('Rate (bps)')
                    ->placeholder('-'),
                TextColumn::make('effective_from')
                    ->date()
                    ->placeholder('-'),
                TextColumn::make('effective_to')
                    ->date()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Version')
                    ->using(function (array $data, RelationManager $livewire): ContractVersion {
                        /** @var CommercialContract $contract */
                        $contract = $livewire->getOwnerRecord();

                        return app(ContractVersioningService::class)->createVersion($contract, $data);
                    }),
            ])
            ->recordActions([
                ViewAction::make()
            ]);
    }
}
