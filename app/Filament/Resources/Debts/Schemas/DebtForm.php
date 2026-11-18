<?php

namespace App\Filament\Resources\Debts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DebtForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Case Details')
                    ->description('Link this overdue receivable to its school, student, financial guardian, and enrollment contract.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('school_id')
                            ->relationship('school', 'name')
                            ->placeholder('Select a school')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('student_id', null);
                                $set('guardian_id', null);
                                $set('enrollment_contract_id', null);
                            })
                            ->required(),
                        Select::make('student_id')
                            ->relationship(
                                name: 'student',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->when(
                                    $get('school_id'),
                                    fn (Builder $q, int $schoolId): Builder => $q->where('school_id', $schoolId),
                                ),
                            )
                            ->placeholder('Select a student')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Select::make('guardian_id')
                            ->relationship(
                                name: 'guardian',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->when(
                                    $get('school_id'),
                                    fn (Builder $q, int $schoolId): Builder => $q->where('school_id', $schoolId),
                                ),
                            )
                            ->placeholder('Select the responsible guardian')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('enrollment_contract_id')
                            ->relationship(
                                name: 'enrollmentContract',
                                titleAttribute: 'contract_number',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                    ->when(
                                        $get('school_id'),
                                        fn (Builder $q, int $schoolId): Builder => $q->where('school_id', $schoolId),
                                    )
                                    ->when(
                                        $get('student_id'),
                                        fn (Builder $q, int $studentId): Builder => $q->where('student_id', $studentId),
                                    ),
                            )
                            ->placeholder('Optional enrollment contract')
                            ->searchable()
                            ->preload(),
                        TextInput::make('external_reference')
                            ->label('External reference')
                            ->placeholder('e.g. ERP-2026-9912')
                            ->helperText('School ERP / billing ID used to prevent duplicate imports.'),
                        Select::make('status')
                            ->options([
                                'imported' => 'Imported',
                                'pending_verification' => 'Pending Verification',
                                'ready_for_collection' => 'Ready for Collection',
                                'active_collection' => 'Active Collection',
                                'negotiating' => 'Negotiating',
                                'agreed' => 'Agreed',
                                'settled' => 'Settled',
                                'written_off' => 'Written Off',
                            ])
                            ->default('imported')
                            ->required(),
                    ]),
                Section::make('Amounts (Centavos)')
                    ->description('All monetary amounts are stored in integer minor units (100 = R$ 1,00) to avoid rounding errors.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('principal_cents')
                            ->label('Principal (centavos)')
                            ->placeholder('e.g. 150000 for R$ 1.500,00')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('interest_cents')
                            ->label('Interest (centavos)')
                            ->placeholder('0')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('fine_cents')
                            ->label('Fine (centavos)')
                            ->placeholder('0')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('correction_cents')
                            ->label('Monetary correction (centavos)')
                            ->placeholder('0')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ]),
                Section::make('Operational Holds')
                    ->description('Hold flags pause collection or invoicing without changing the debt lifecycle status.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_disputed')
                            ->label('Disputed')
                            ->helperText('Pauses collection while dispute is reviewed.')
                            ->default(false),
                        Toggle::make('is_suspended')
                            ->label('Suspended')
                            ->helperText('Temporarily paused by school or legal.')
                            ->default(false),
                        Toggle::make('under_payment_verification')
                            ->label('Under Payment Verification')
                            ->helperText('Awaiting confirmation of a direct/external payment.')
                            ->default(false),
                    ]),
                Section::make('Notes')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('notes')
                            ->placeholder('Context about this receivable, origin, or school instructions...')
                            ->rows(3),
                    ]),
            ]);
    }
}
