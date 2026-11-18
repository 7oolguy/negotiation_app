<?php

namespace App\Filament\Resources\Guardians\Schemas;

use App\Models\Guardian;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class GuardianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->description('Select the school, enter the guardian\'s CPF/CNPJ, and link their students.')
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
                            ->placeholder('Full legal name')
                            ->required()
                            ->maxLength(255),
                        Select::make('document_type')
                            ->options([
                                'cpf' => 'CPF (Individual)',
                                'cnpj' => 'CNPJ (Company)',
                            ])
                            ->default('cpf')
                            ->live()
                            ->required(),
                        TextInput::make('document')
                            ->label('CPF / CNPJ')
                            ->placeholder(fn (Get $get): string => $get('document_type') === 'cnpj'
                                ? '00.000.000/0000-00'
                                : '000.000.000-00')
                            ->mask(fn (Get $get): string => $get('document_type') === 'cnpj'
                                ? '99.999.999/9999-99'
                                : '999.999.999-99')
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? preg_replace('/\D/', '', $state) : null)
                            ->live(onBlur: true)
                            ->rule(function (Get $get, ?Guardian $record): Closure {
                                return function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $digits = preg_replace('/\D/', '', (string) $value);
                                    $schoolId = $get('school_id');

                                    if ($digits === '' || ! $schoolId) {
                                        return;
                                    }

                                    $exists = Guardian::query()
                                        ->where('school_id', $schoolId)
                                        ->where('document', $digits)
                                        ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey()))
                                        ->exists();

                                    if ($exists) {
                                        $fail('A guardian with this CPF/CNPJ already exists in the selected school.');
                                    }
                                };
                            })
                            ->helperText(function (?string $state, Get $get, ?Guardian $record): HtmlString|string {
                                $digits = preg_replace('/\D/', '', (string) $state);
                                $schoolId = $get('school_id');

                                if ($digits !== '' && $schoolId) {
                                    $existing = Guardian::query()
                                        ->where('school_id', $schoolId)
                                        ->where('document', $digits)
                                        ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey()))
                                        ->first();

                                    if ($existing) {
                                        return new HtmlString(
                                            '<span class="text-danger-600 dark:text-danger-400">Duplicate CPF/CNPJ found in this school: '
                                            .e($existing->name).'</span>'
                                        );
                                    }
                                }

                                return 'Stored as digits only and checked for duplicates per school.';
                            })
                            ->required(),
                        Select::make('students')
                            ->relationship(
                                name: 'students',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->when(
                                    $get('school_id'),
                                    fn (Builder $q, int $schoolId): Builder => $q->where('school_id', $schoolId),
                                ),
                            )
                            ->placeholder('Select one or more students')
                            ->helperText('One guardian can be responsible for multiple students in the same school.')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Contact & Address')
                    ->description('Channels used for respectful collection outreach and payment links.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->label('Email address')
                            ->placeholder('guardian@example.com')
                            ->email(),
                        TextInput::make('phone')
                            ->tel()
                            ->mask('(99) 99999-9999')
                            ->placeholder('(11) 99999-9999'),
                        TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->tel()
                            ->mask('(99) 99999-9999')
                            ->placeholder('(11) 99999-9999'),
                        TextInput::make('zip_code')
                            ->label('CEP')
                            ->mask('99999-999')
                            ->placeholder('00000-000')
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? preg_replace('/\D/', '', $state) : null),
                        TextInput::make('address')
                            ->placeholder('Street, number, complement')
                            ->columnSpanFull(),
                        TextInput::make('city')
                            ->placeholder('São Paulo'),
                        TextInput::make('state')
                            ->label('UF')
                            ->placeholder('SP')
                            ->maxLength(2),
                        Textarea::make('notes')
                            ->placeholder('Internal notes about preferred contact hours or guardian context...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
