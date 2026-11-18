<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Debts\DebtResource;
use App\Models\CollectionTask;
use App\Models\User;
use App\Services\DebtLifecycleService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TodaysCollectionTasksWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->isSchoolScoped()) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'superadmin', 'manager', 'collector', 'legal', 'finance'])
            || $user->can('View:TodaysCollectionTasksWidget');
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->heading('Tarefas de Hoje')
            ->description('Lembretes e retornos pendentes com vencimento até hoje (incluindo atrasados).')
            ->query(function () use ($user): Builder {
                $query = CollectionTask::query()
                    ->with(['debt.school', 'debt.student', 'debt.guardian', 'assignee'])
                    ->dueTodayOrOverdue()
                    ->orderBy('due_at');

                if (! $user instanceof User || $user->isSchoolScoped()) {
                    return $query->whereRaw('1 = 0');
                }

                if ($user->isCollectorOnly()) {
                    $query->forAssignee($user);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('due_at')
                    ->label('Prazo')
                    ->dateTime('d/m/Y H:i')
                    ->badge()
                    ->color(fn (CollectionTask $record): string => $record->isOverdue() ? 'danger' : 'warning')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Tarefa')
                    ->description(fn (CollectionTask $record): ?string => $record->description)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('debt.guardian.name')
                    ->label('Responsável')
                    ->searchable(),
                TextColumn::make('debt.student.name')
                    ->label('Aluno')
                    ->searchable(),
                TextColumn::make('debt.school.name')
                    ->label('Escola')
                    ->toggleable(),
                TextColumn::make('priority')
                    ->label('Prioridade')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CollectionTask::PRIORITIES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('assignee.name')
                    ->label('Cobrador')
                    ->visible(fn (): bool => $user instanceof User && ! $user->isCollectorOnly()),
            ])
            ->recordActions([
                Action::make('openCase')
                    ->label('Abrir Caso')
                    ->icon(Heroicon::Eye)
                    ->url(fn (CollectionTask $record): string => DebtResource::getUrl('view', ['record' => $record->debt_id])),
                Action::make('complete')
                    ->label('Concluir')
                    ->icon(Heroicon::CheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Concluir tarefa')
                    ->modalDescription('Marcar este lembrete de cobrança como concluído?')
                    ->action(function (CollectionTask $record): void {
                        app(DebtLifecycleService::class)->completeTask($record);

                        Notification::make()
                            ->title('Tarefa concluída')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhuma tarefa pendente para hoje')
            ->emptyStateDescription('Quando houver retornos ou lembretes agendados até hoje, eles aparecerão aqui.');
    }
}
