<?php

namespace App\Filament\Resources\LegalEntities\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LegalEntityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('legal_name')
                ->placeholder('Legal Name Ltd.')
                    ->required(),
                TextInput::make('trade_name')
                    ->placeholder('Corporation X'),
                TextInput::make('cnpj')
                    ->label('CNPJ')
                    ->mask('99.999.999/9999-99')
                    ->placeholder('00.000.000/0000-00')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->placeholder('exemple@email.com')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->mask('(99) 99999-9999')
                    ->placeholder('(XX) XXXXX-XXXX')
                    ->required(),
                TextInput::make('address')
                    ->placeholder('Street example - 123, neighborhood, city')
                    ->required(),
                TextInput::make('city')
                    ->placeholder('City Blue')
                    ->required(),
                TextInput::make('state')
                    ->placeholder('Blue State')
                    ->required(),
                TextInput::make('zip_code')
                    ->mask('99999-999')
                    ->placeholder('00000-000')
                    ->required(),
                TextInput::make('country')
                    ->placeholder('Brazil')
                    ->default('Brazil')
                    ->required(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
