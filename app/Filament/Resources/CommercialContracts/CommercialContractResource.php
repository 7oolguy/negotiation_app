<?php

namespace App\Filament\Resources\CommercialContracts;

use App\Filament\Resources\CommercialContracts\Pages\CreateCommercialContract;
use App\Filament\Resources\CommercialContracts\Pages\EditCommercialContract;
use App\Filament\Resources\CommercialContracts\Pages\ListCommercialContracts;
use App\Filament\Resources\CommercialContracts\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\CommercialContracts\Schemas\CommercialContractForm;
use App\Filament\Resources\CommercialContracts\Tables\CommercialContractsTable;
use App\Models\CommercialContract;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CommercialContractResource extends Resource
{
    protected static ?string $model = CommercialContract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Resources';

    public static function form(Schema $schema): Schema
    {
        return CommercialContractForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommercialContractsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommercialContracts::route('/'),
            'create' => CreateCommercialContract::route('/create'),
            'edit' => EditCommercialContract::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
