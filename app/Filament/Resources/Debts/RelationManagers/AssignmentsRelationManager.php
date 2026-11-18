<?php

namespace App\Filament\Resources\Debts\RelationManagers;

use App\Models\Debt;
use App\Models\DebtAssignment;
use App\Models\User;
use App\Services\DebtLifecycleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Histórico de Atribuições';

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $canManage = $user instanceof User
            && ! $user->isSchoolScoped()
            && ($user->can('Create:DebtAssignment') || $user->hasAnyRole(['super_admin', 'superadmin', 'manager']));

        return $table
            ->defaultSort('assigned_at', 'desc')
            ->columns([
                TextColumn::make('collector.name')
                    ->label('Cobrador'),
                TextColumn::make('assigned_at')
                    ->label('Atribuído em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->label('Encerrado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Ativa')
                    ->badge()
                    ->color(fn (DebtAssignment $record): string => $record->isActive() ? 'success' : 'gray'),
                TextColumn::make('end_reason')
                    ->label('Motivo de Encerramento')
                    ->placeholder('-'),
                TextColumn::make('assignedBy.name')
                    ->label('Atribuído por')
                    ->placeholder('-'),
                TextColumn::make('notes')
                    ->label('Observações')
                    ->wrap()
                    ->placeholder('-'),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label('Atribuir Cobrador')
                    ->icon(Heroicon::UserPlus)
                    ->visible($canManage)
                    ->schema([
                        Select::make('collector_id')
                            ->label('Cobrador')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(2),
                    ])
                    ->action(function (array $data): void {
                        /** @var Debt $debt */
                        $debt = $this->getOwnerRecord();
                        $collector = User::findOrFail($data['collector_id']);

                        app(DebtLifecycleService::class)->assignCollector(
                            debt: $debt,
                            collector: $collector,
                            notes: $data['notes'] ?? null,
                        );

                        Notification::make()
                            ->title('Cobrador atribuído')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('end')
                    ->label('Encerrar')
                    ->color('danger')
                    ->visible(fn (DebtAssignment $record): bool => $canManage && $record->isActive())
                    ->requiresConfirmation()
                    ->action(function (): void {
                        /** @var Debt $debt */
                        $debt = $this->getOwnerRecord();

                        app(DebtLifecycleService::class)->endAssignment($debt, 'manual_end');

                        Notification::make()
                            ->title('Atribuição encerrada')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
