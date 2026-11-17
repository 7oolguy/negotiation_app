<?php

namespace App\Filament\Resources\LegalEntities;

use App\Filament\Resources\LegalEntities\Pages\CreateLegalEntity;
use App\Filament\Resources\LegalEntities\Pages\EditLegalEntity;
use App\Filament\Resources\LegalEntities\Pages\ListLegalEntities;
use App\Filament\Resources\LegalEntities\Schemas\LegalEntityForm;
use App\Filament\Resources\LegalEntities\Tables\LegalEntitiesTable;
use App\Models\LegalEntity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class LegalEntityResource extends Resource
{
    protected static ?string $model = LegalEntity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice2;

    protected static ?string $pluralModelLabel = 'Legal Entities';

    protected static ?string $modelLabel = 'Legal Entity';

    protected static string|UnitEnum|null $navigationGroup = 'Resources';

    public static function form(Schema $schema): Schema
    {
        return LegalEntityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegalEntitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalEntities::route('/'),
            'create' => CreateLegalEntity::route('/create'),
            'edit' => EditLegalEntity::route('/{record}/edit'),
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
