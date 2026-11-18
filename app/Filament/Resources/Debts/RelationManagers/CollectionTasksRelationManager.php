<?php

namespace App\Filament\Resources\Debts\RelationManagers;

use App\Models\CollectionTask;
use App\Models\Debt;
use App\Models\User;
use App\Services\DebtLifecycleService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CollectionTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'collectionTasks';

    protected static ?string $title = 'Tarefas e Lembretes';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = Auth::user();

        return $user instanceof User && ! $user->isSchoolScoped();
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user): Builder {
                if ($user instanceof User) {
                    $query->forUser($user);
                }

                return $query->with('assignee');
            })
            ->defaultSort('due_at', 'asc')
            ->columns([
                TextColumn::make('due_at')
                    ->label('Prazo')
                    ->dateTime('d/m/Y H:i')
                    ->badge()
                    ->color(fn (CollectionTask $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Tarefa')
                    ->description(fn (CollectionTask $record): ?string => $record->description)
                    ->wrap(),
                TextColumn::make('assignee.name')
                    ->label('Responsável'),
                TextColumn::make('priority')
                    ->label('Prioridade')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CollectionTask::PRIORITIES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => CollectionTask::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        CollectionTask::STATUS_COMPLETED => 'success',
                        CollectionTask::STATUS_PENDING => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('createTask')
                    ->label('Nova Tarefa')
                    ->icon(Heroicon::Plus)
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required(),
                        DateTimePicker::make('due_at')
                            ->label('Prazo')
                            ->default(now()->addDay())
                            ->required(),
                        Select::make('priority')
                            ->label('Prioridade')
                            ->options(CollectionTask::PRIORITIES)
                            ->default('normal')
                            ->required(),
                        Select::make('assigned_to')
                            ->label('Responsável')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => Auth::id())
                            ->searchable()
                            ->required(),
                        Textarea::make('description')
                            ->label('Descrição')
                            ->rows(2),
                    ])
                    ->action(function (array $data): void {
                        /** @var Debt $debt */
                        $debt = $this->getOwnerRecord();
                        $assignee = User::findOrFail($data['assigned_to']);

                        app(DebtLifecycleService::class)->scheduleTask(
                            debt: $debt,
                            assignee: $assignee,
                            title: $data['title'],
                            dueAt: now()->parse($data['due_at']),
                            description: $data['description'] ?? null,
                            priority: $data['priority'] ?? 'normal',
                        );

                        Notification::make()
                            ->title('Tarefa agendada')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Concluir')
                    ->icon(Heroicon::CheckCircle)
                    ->color('success')
                    ->visible(fn (CollectionTask $record): bool => $record->isPending())
                    ->action(function (CollectionTask $record): void {
                        app(DebtLifecycleService::class)->completeTask($record);

                        Notification::make()
                            ->title('Tarefa concluída')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
