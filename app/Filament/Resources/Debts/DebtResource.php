<?php

namespace App\Filament\Resources\Debts;

use App\Filament\Resources\Debts\Pages\CreateDebt;
use App\Filament\Resources\Debts\Pages\EditDebt;
use App\Filament\Resources\Debts\Pages\ListDebts;
use App\Filament\Resources\Debts\Pages\ViewDebt;
use App\Filament\Resources\Debts\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\Debts\RelationManagers\CollectionActionsRelationManager;
use App\Filament\Resources\Debts\RelationManagers\CollectionTasksRelationManager;
use App\Filament\Resources\Debts\Schemas\DebtForm;
use App\Filament\Resources\Debts\Schemas\DebtInfolist;
use App\Filament\Resources\Debts\Tables\DebtsTable;
use App\Models\Debt;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DebtResource extends Resource
{
    protected static ?string $model = Debt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CreditCard;

    protected static ?string $modelLabel = 'Dívida';

    protected static ?string $pluralModelLabel = 'Dívidas';

    protected static string|UnitEnum|null $navigationGroup = 'Cobrança';

    public static function form(Schema $schema): Schema
    {
        return DebtForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DebtInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DebtsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CollectionActionsRelationManager::class,
            CollectionTasksRelationManager::class,
            AssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDebts::route('/'),
            'create' => CreateDebt::route('/create'),
            'view' => ViewDebt::route('/{record}'),
            'edit' => EditDebt::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user instanceof User) {
            $query->forUser($user);
        }

        return $query;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        $query = parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $user = Auth::user();

        if ($user instanceof User) {
            $query->forUser($user);
        }

        return $query;
    }
}
