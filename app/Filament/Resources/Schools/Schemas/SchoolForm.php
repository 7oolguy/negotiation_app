<?php

namespace App\Filament\Resources\Schools\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('legal_entity_id')
                    ->relationship('legalEntity', 'legal_name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->placeholder('School Name')
                    ->required(),
                TextInput::make('legal_name')
                    ->placeholder('Legal Name')
                    ->required(),
                TextInput::make('cnpj')
                    ->mask('99.999.999/9999-99')
                    ->placeholder('00.000.000/0000-00')
                    ->required(),
                TextInput::make('email')
                    ->placeholder('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->mask('(99) 99999-9999')
                    ->placeholder('(00) 00000-0000')
                    ->required(),
                TextInput::make('address')
                    ->placeholder('Address Name - 123, Complement')
                    ->required(),
                TextInput::make('city')
                    ->placeholder('City Name')
                    ->required(),
                TextInput::make('state')
                    ->placeholder('State Name')
                    ->required(),
                TextInput::make('zip_code')
                    ->placeholder('00000-000')
                    ->mask('99999-999')
                    ->required(),
                TextInput::make('country')
                    ->placeholder('Country Name')
                    ->required()
                    ->default('Brasil'),
                TextInput::make('website')
                    ->placeholder('www.example.com')
                    ->url(),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                Section::make('Files')
                    ->description('Upload visual identity of your school, it will be used for customization of the dashboard.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        FileUpload::make('logo')
                            ->image()
                            ->disk('public')
                            ->directory('schools/logos')
                            ->acceptedFileTypes(['image/jpeg', 'image/webp', 'image/png', 'image/svg+xml']),
                        FileUpload::make('favicon')
                            ->image()
                            ->disk('public')
                            ->directory('schools/favicons')
                            ->acceptedFileTypes(['image/x-icon', 'image/vnd.microsoft.icon', 'image/jpeg', 'image/webp', 'image/png', 'image/svg+xml']),
                    ])
            ]);
    }
}
