<?php

namespace App\Filament\Resources\Debts\Pages;

use App\Filament\Resources\Debts\DebtResource;
use App\Models\CollectionAction;
use App\Models\CollectionTask;
use App\Models\Debt;
use App\Models\User;
use App\Services\DebtLifecycleService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * @property Debt $record
 */
class ViewDebt extends ViewRecord
{
    protected static string $resource = DebtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('quickLog')
                ->label('Registrar Ação')
                ->icon(Heroicon::Phone)
                ->visible(fn (): bool => $this->canOperate())
                ->modalHeading('Registro Rápido de Cobrança')
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
                        ->rows(3),
                    FileUpload::make('evidence_path')
                        ->label('Evidência (disco privado)')
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
                ->action(function (array $data): void {
                    $service = app(DebtLifecycleService::class);

                    try {
                        $action = $service->logAction($this->record, $data);

                        if (! empty($data['schedule_followup']) && ! empty($data['followup_title']) && ! empty($data['followup_due_at'])) {
                            $assignee = $this->record->activeAssignment?->collector ?? Auth::user();

                            if ($assignee instanceof User) {
                                $service->scheduleTask(
                                    debt: $this->record,
                                    assignee: $assignee,
                                    title: $data['followup_title'],
                                    dueAt: now()->parse($data['followup_due_at']),
                                    sourceAction: $action,
                                );
                            }
                        }

                        $this->record->refresh();

                        Notification::make()
                            ->title('Ação registrada')
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

            Action::make('transitionStatus')
                ->label('Avançar Status')
                ->icon(Heroicon::ArrowRightCircle)
                ->visible(fn (): bool => $this->canOperate()
                    && app(DebtLifecycleService::class)->allowedNextStatuses($this->record) !== [])
                ->schema([
                    Select::make('target_status')
                        ->label('Novo Status')
                        ->options(function (): array {
                            $allowed = app(DebtLifecycleService::class)->allowedNextStatuses($this->record);

                            return collect($allowed)
                                ->mapWithKeys(fn (string $status): array => [$status => Debt::STATUSES[$status] ?? $status])
                                ->all();
                        })
                        ->required(),
                    Textarea::make('reason')
                        ->label('Justificativa')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    try {
                        app(DebtLifecycleService::class)->transition(
                            debt: $this->record,
                            targetStatus: $data['target_status'],
                            reason: $data['reason'] ?? null,
                        );

                        $this->record->refresh();

                        Notification::make()
                            ->title('Status atualizado')
                            ->success()
                            ->send();
                    } catch (DomainException $e) {
                        Notification::make()
                            ->title('Transição bloqueada')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('manageHold')
                ->label('Retenções')
                ->icon(Heroicon::PauseCircle)
                ->color(fn (): string => $this->record->isCollectionPaused() ? 'warning' : 'gray')
                ->visible(fn (): bool => $this->canOperate())
                ->schema([
                    Select::make('hold_type')
                        ->label('Tipo de Retenção')
                        ->options([
                            DebtLifecycleService::HOLD_DISPUTED => 'Contestação (Disputed)',
                            DebtLifecycleService::HOLD_SUSPENDED => 'Suspensão (Suspended)',
                            DebtLifecycleService::HOLD_PAYMENT_VERIFICATION => 'Verificação de Pagamento',
                        ])
                        ->required(),
                    Select::make('operation')
                        ->label('Ação')
                        ->options([
                            'place' => 'Ativar retenção (pausar cobrança)',
                            'release' => 'Liberar retenção',
                        ])
                        ->default('place')
                        ->required(),
                    Textarea::make('reason')
                        ->label('Motivo')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $service = app(DebtLifecycleService::class);

                    if ($data['operation'] === 'place') {
                        $service->placeHold($this->record, $data['hold_type'], $data['reason'] ?? null);
                        $message = 'Retenção ativada — cobrança pausada';
                    } else {
                        $service->releaseHold($this->record, $data['hold_type'], $data['reason'] ?? null);
                        $message = 'Retenção liberada';
                    }

                    $this->record->refresh();

                    Notification::make()
                        ->title($message)
                        ->success()
                        ->send();
                }),

            Action::make('assignCollector')
                ->label('Atribuir Cobrador')
                ->icon(Heroicon::UserPlus)
                ->visible(fn (): bool => $this->canAssign())
                ->schema([
                    Select::make('collector_id')
                        ->label('Cobrador')
                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                    Toggle::make('activate_if_ready')
                        ->label('Iniciar cobrança ativa se estiver "Pronta para Cobrança"')
                        ->default(false),
                    Textarea::make('notes')
                        ->label('Observações da atribuição')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $collector = User::findOrFail($data['collector_id']);

                    try {
                        app(DebtLifecycleService::class)->assignCollector(
                            debt: $this->record,
                            collector: $collector,
                            notes: $data['notes'] ?? null,
                            activateIfReady: (bool) ($data['activate_if_ready'] ?? false),
                        );

                        $this->record->refresh();

                        Notification::make()
                            ->title('Cobrador atribuído')
                            ->success()
                            ->send();
                    } catch (DomainException $e) {
                        Notification::make()
                            ->title('Atribuição bloqueada')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('scheduleTask')
                ->label('Nova Tarefa')
                ->icon(Heroicon::ClipboardDocumentList)
                ->visible(fn (): bool => $this->canOperate())
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
                        ->default(fn (): ?int => $this->record->activeAssignment?->collector_id ?? Auth::id())
                        ->searchable()
                        ->required(),
                    Textarea::make('description')
                        ->label('Descrição')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $assignee = User::findOrFail($data['assigned_to']);

                    app(DebtLifecycleService::class)->scheduleTask(
                        debt: $this->record,
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

            EditAction::make(),
        ];
    }

    private function canOperate(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ! $user->isSchoolScoped();
    }

    private function canAssign(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->isSchoolScoped()) {
            return false;
        }

        return $user->can('Create:DebtAssignment')
            || $user->hasAnyRole(['super_admin', 'superadmin', 'manager']);
    }
}
