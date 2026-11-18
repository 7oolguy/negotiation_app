<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Student Information')
                    ->description('School enrollment details and academic status.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('school_id')
                            ->relationship('school', 'name')
                            ->placeholder('Select a school')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        TextInput::make('name')
                            ->placeholder('Student full name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('cpf')
                            ->label('CPF')
                            ->placeholder('000.000.000-00')
                            ->mask('999.999.999-99')
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? preg_replace('/\D/', '', $state) : null),
                        TextInput::make('enrollment_code')
                            ->label('Enrollment code (RA)')
                            ->placeholder('e.g. 2026-00412')
                            ->helperText('Unique student ID / RA within the selected school.')
                            ->unique(
                                table: 'students',
                                column: 'enrollment_code',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('school_id', $get('school_id')),
                            ),
                        DatePicker::make('birth_date')
                            ->placeholder('dd/mm/yyyy'),
                        TextInput::make('grade_level')
                            ->placeholder('e.g. 5º Ano EF / 2ª Série EM'),
                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'graduated' => 'Graduated',
                            ])
                            ->default('active')
                            ->required(),
                    ]),
                Section::make('Guardians')
                    ->description('Link one or more financial/pedagogical guardians for this student.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('guardians')
                            ->relationship(
                                name: 'guardians',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->when(
                                    $get('school_id'),
                                    fn (Builder $q, int $schoolId): Builder => $q->where('school_id', $schoolId),
                                ),
                            )
                            ->placeholder('Select one or more guardians')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
            ]);
    }
}
