<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Debts\DebtResource;
use App\Filament\Widgets\TodaysCollectionTasksWidget;
use App\Models\CollectionAction;
use App\Models\CollectionTask;
use App\Models\Debt;
use App\Models\User;
use App\Services\DebtLifecycleService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CollectorWorkspace extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.collector-workspace';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Briefcase;

    protected static ?string $navigationLabel = 'Mesa do Cobrador';

    protected static ?string $title = 'Mesa do Cobrador';

    protected static string|UnitEnum|null $navigationGroup = 'Cobrança';

    protected static ?string $slug = 'collector-workspace';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->isSchoolScoped()) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'superadmin', 'manager', 'collector', 'legal'])
            || $user->can('View:CollectorWorkspace');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TodaysCollectionTasksWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->heading('Carteira Atribuída')
            ->description('Casos com atribuição ativa para acompanhamento e registro de ações.')
            ->query(function () use ($user): Builder {
                $query = Debt::query()
                    ->with(['school', 'student', 'guardian', 'activeAssignment.collector'])
                    ->withCount([
                        'collectionActions',
                        'collectionTasks as pending_tasks_count' => fn (Builder $q) => $q->pending(),
                    ]);

                if (! $user instanceof User || $user->isSchoolScoped()) {
                    return $query->whereRaw('1 = 0');
                }

                if ($user->isCollectorOnly()) {
                    return $query->assignedTo($user);
                }

                return $query->whereHas('activeAssignment');
            })
            ->columns([
                TextColumn::make('external_reference')
                    ->label('Referência')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('guardian.name')
                    ->label('Responsável')
                    ->searchable(),
                TextColumn::make('student.name')
                    ->label('Aluno')
                    ->searchable(),
                TextColumn::make('school.name')
                    ->label('Escola')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('activeAssignment.collector.name')
                    ->label('Cobrador')
                    ->visible(fn (): bool => $user instanceof User && ! $user->isCollectorOnly()),
                TextColumn::make('principal_cents')
                    ->label('Total')
                    ->state(fn (Debt $record): int => $record->totalAmountCents())
                    ->formatStateUsing(fn (int $state): string => 'R$ '.number_format($state / 100, 2, ',', '.'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Debt::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Debt::STATUS_ACTIVE_COLLECTION, Debt::STATUS_NEGOTIATING => 'warning',
                        Debt::STATUS_AGREED, Debt::STATUS_SETTLED => 'success',
                        Debt::STATUS_WRITTEN_OFF => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('hold_status')
                    ->label('Situação')
                    ->state(function (Debt $record): string {
                        if ($record->is_disputed) {
                            return 'Contestado';
                        }
                        if ($record->is_suspended) {
                            return 'Suspenso';
                        }
                        if ($record->under_payment_verification) {
                            return 'Verif. Pgto';
                        }

                        return 'Liberado';
                    })
                    ->badge()
                    ->color(fn (Debt $record): string => $record->isCollectionPaused() ? 'danger' : 'success'),
                TextColumn::make('pending_tasks_count')
                    ->label('Tarefas')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Debt::STATUSES),
                TernaryFilter::make('paused')
                    ->label('Retenção Ativa')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(function (Builder $q): void {
                            $q->where('is_disputed', true)
                                ->orWhere('is_suspended', true)
                                ->orWhere('under_payment_verification', true);
                        }),
                        false: fn (Builder $query): Builder => $query
                            ->where('is_disputed', false)
                            ->where('is_suspended', false)
                            ->where('under_payment_verification', false),
                    ),
                SelectFilter::make('collector_id')
                    ->label('Cobrador')
                    ->visible(fn (): bool => $user instanceof User && ! $user->isCollectorOnly())
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, mixed $collectorId): Builder => $q->whereHas(
                            'activeAssignment',
                            fn (Builder $aq) => $aq->where('collector_id', $collectorId),
                        ),
                    )),
            ])
            ->recordActions([
                Action::make('openCase')
                    ->label('Ver Caso')
                    ->icon(Heroicon::Eye)
                    ->url(fn (Debt $record): string => DebtResource::getUrl('view', ['record' => $record])),
                Action::make('quickLog')
                    ->label('Registro Rápido')
                    ->icon(Heroicon::Phone)
                    ->modalHeading(fn (Debt $record): string => 'Registrar Ação — '.($record->guardian?->name ?? "Dívida #{$record->id}"))
                    ->schema([
                        Select::make('channel')
                            ->label('Canal')
                            ->options(CollectionAction::CHANNELS)
                            ->default(CollectionAction::CHANNEL_WHATSAPP)
                            ->live()
                            ->required(),
                        Select::make('outcome')
                            ->label('Resultado')
                            ->options(CollectionAction::OUTCOMES)
                            ->default('contacted')
                            ->required(),
                        Toggle::make('is_internal')
                            ->label('Nota interna (oculta para a escola)')
                            ->helperText('Notas internas nunca são exibidas para usuários da escola.')
                            ->default(false),
                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->placeholder('Descreva o atendimento ou observação interna...'),
                        FileUpload::make('evidence_path')
                            ->label('Evidência (armazenamento privado)')
                            ->disk('private')
                            ->directory('collection-evidence')
                            ->visibility('private')
                            ->storeFileNamesIn('evidence_original_name'),
                        Toggle::make('schedule_followup')
                            ->label('Agendar tarefa de retorno')
                            ->live()
                            ->default(false),
                        TextInput::make('followup_title')
                            ->label('Título da tarefa')
                            ->visible(fn (Get $get): bool => (bool) $get('schedule_followup'))
                            ->required(fn (Get $get): bool => (bool) $get('schedule_followup')),
                        DateTimePicker::make('followup_due_at')
                            ->label('Prazo do retorno')
                            ->visible(fn (Get $get): bool => (bool) $get('schedule_followup'))
                            ->required(fn (Get $get): bool => (bool) $get('schedule_followup')),
                    ])
                    ->action(function (Debt $record, array $data): void {
                        $service = app(DebtLifecycleService::class);

                        try {
                            $action = $service->logAction($record, $data);

                            if (! empty($data['schedule_followup']) && ! empty($data['followup_title']) && ! empty($data['followup_due_at'])) {
                                $assignee = $record->activeAssignment?->collector ?? Auth::user();

                                if ($assignee instanceof User) {
                                    $service->scheduleTask(
                                        debt: $record,
                                        assignee: $assignee,
                                        title: $data['followup_title'],
                                        dueAt: now()->parse($data['followup_due_at']),
                                        sourceAction: $action,
                                    );
                                }
                            }

                            Notification::make()
                                ->title('Ação registrada com sucesso')
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title('Ação bloqueada')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('Nenhum caso atribuído')
            ->emptyStateDescription('Atribua um cobrador a uma dívida para que ela apareça nesta carteira.');
    }
}
