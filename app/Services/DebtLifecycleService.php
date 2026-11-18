<?php

namespace App\Services;

use App\Models\CollectionAction;
use App\Models\CollectionTask;
use App\Models\Debt;
use App\Models\DebtAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DebtLifecycleService
{
    public const HOLD_DISPUTED = 'disputed';
    public const HOLD_SUSPENDED = 'suspended';
    public const HOLD_PAYMENT_VERIFICATION = 'payment_verification';

    /** @var array<string, list<string>> */
    public const ALLOWED_TRANSITIONS = [
        Debt::STATUS_IMPORTED => [
            Debt::STATUS_PENDING_VERIFICATION,
            Debt::STATUS_WRITTEN_OFF,
        ],
        Debt::STATUS_PENDING_VERIFICATION => [
            Debt::STATUS_READY_FOR_COLLECTION,
            Debt::STATUS_WRITTEN_OFF,
        ],
        Debt::STATUS_READY_FOR_COLLECTION => [
            Debt::STATUS_ACTIVE_COLLECTION,
            Debt::STATUS_PENDING_VERIFICATION,
            Debt::STATUS_SETTLED,
            Debt::STATUS_WRITTEN_OFF,
        ],
        Debt::STATUS_ACTIVE_COLLECTION => [
            Debt::STATUS_NEGOTIATING,
            Debt::STATUS_AGREED,
            Debt::STATUS_SETTLED,
            Debt::STATUS_WRITTEN_OFF,
        ],
        Debt::STATUS_NEGOTIATING => [
            Debt::STATUS_AGREED,
            Debt::STATUS_ACTIVE_COLLECTION,
            Debt::STATUS_SETTLED,
            Debt::STATUS_WRITTEN_OFF,
        ],
        Debt::STATUS_AGREED => [
            Debt::STATUS_SETTLED,
            Debt::STATUS_ACTIVE_COLLECTION,
            Debt::STATUS_WRITTEN_OFF,
        ],
        Debt::STATUS_SETTLED => [],
        Debt::STATUS_WRITTEN_OFF => [],
    ];

    /** @var list<string> */
    private const COLLECTION_ACTIVE_STATUSES = [
        Debt::STATUS_ACTIVE_COLLECTION,
        Debt::STATUS_NEGOTIATING,
        Debt::STATUS_AGREED,
    ];

    /** @var array<string, string> */
    private const HOLD_COLUMNS = [
        self::HOLD_DISPUTED => 'is_disputed',
        self::HOLD_SUSPENDED => 'is_suspended',
        self::HOLD_PAYMENT_VERIFICATION => 'under_payment_verification',
    ];

    /**
     * @return list<string>
     */
    public function allowedNextStatuses(Debt $debt): array
    {
        $targets = self::ALLOWED_TRANSITIONS[$debt->status] ?? [];

        if ($debt->isCollectionPaused()) {
            return array_values(array_diff($targets, self::COLLECTION_ACTIVE_STATUSES));
        }

        return $targets;
    }

    public function canTransition(Debt $debt, string $targetStatus): bool
    {
        return in_array($targetStatus, $this->allowedNextStatuses($debt), true);
    }

    public function transition(
        Debt $debt,
        string $targetStatus,
        ?User $actor = null,
        ?string $reason = null,
    ): Debt {
        if (! array_key_exists($targetStatus, Debt::STATUSES)) {
            throw new InvalidArgumentException("Status de dívida inválido: {$targetStatus}.");
        }

        $currentStatus = $debt->status;
        $allowedByGraph = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($targetStatus, $allowedByGraph, true)) {
            throw new DomainException(
                "Transição de status não permitida: {$currentStatus} → {$targetStatus}."
            );
        }

        if (in_array($targetStatus, self::COLLECTION_ACTIVE_STATUSES, true) && $debt->isCollectionPaused()) {
            throw new DomainException($this->pauseReasonMessage($debt));
        }

        return DB::transaction(function () use ($debt, $currentStatus, $targetStatus, $actor, $reason): Debt {
            $debt->status = $targetStatus;
            $debt->save();

            AuditLogger::log(
                event: 'debt_status_transitioned',
                auditable: $debt,
                oldValues: ['status' => $currentStatus],
                newValues: ['status' => $targetStatus],
                metadata: array_filter([
                    'actor_id' => $actor?->getKey() ?? Auth::id(),
                    'reason' => $reason,
                ]),
            );

            return $debt->refresh();
        });
    }

    public function placeHold(
        Debt $debt,
        string $holdType,
        ?string $reason = null,
        ?User $actor = null,
    ): Debt {
        $column = $this->resolveHoldColumn($holdType);

        return DB::transaction(function () use ($debt, $column, $holdType, $reason, $actor): Debt {
            $oldValue = (bool) $debt->{$column};
            $debt->{$column} = true;
            $debt->save();

            AuditLogger::log(
                event: 'debt_hold_placed',
                auditable: $debt,
                oldValues: [$column => $oldValue],
                newValues: [$column => true],
                metadata: array_filter([
                    'hold_type' => $holdType,
                    'actor_id' => $actor?->getKey() ?? Auth::id(),
                    'reason' => $reason,
                ]),
            );

            return $debt->refresh();
        });
    }

    public function releaseHold(
        Debt $debt,
        string $holdType,
        ?string $reason = null,
        ?User $actor = null,
    ): Debt {
        $column = $this->resolveHoldColumn($holdType);

        return DB::transaction(function () use ($debt, $column, $holdType, $reason, $actor): Debt {
            $oldValue = (bool) $debt->{$column};
            $debt->{$column} = false;
            $debt->save();

            AuditLogger::log(
                event: 'debt_hold_released',
                auditable: $debt,
                oldValues: [$column => $oldValue],
                newValues: [$column => false],
                metadata: array_filter([
                    'hold_type' => $holdType,
                    'actor_id' => $actor?->getKey() ?? Auth::id(),
                    'reason' => $reason,
                ]),
            );

            return $debt->refresh();
        });
    }

    /**
     * Assign a collector to a debt, closing any existing active assignment in
     * the same transaction so the single-active-assignment invariant holds
     * across SQLite, PostgreSQL, and MySQL alike.
     */
    public function assignCollector(
        Debt $debt,
        User $collector,
        ?User $assignedBy = null,
        ?string $notes = null,
        string $endPreviousReason = 'reassigned',
        bool $activateIfReady = false,
    ): DebtAssignment {
        return DB::transaction(function () use (
            $debt,
            $collector,
            $assignedBy,
            $notes,
            $endPreviousReason,
            $activateIfReady,
        ): DebtAssignment {
            $now = now();

            // Close any currently open assignment inside the lock before inserting the new one.
            $debt->assignments()
                ->active()
                ->lockForUpdate()
                ->get()
                ->each(function (DebtAssignment $existing) use ($now, $endPreviousReason): void {
                    $existing->update([
                        'ended_at' => $now,
                        'end_reason' => $endPreviousReason,
                    ]);
                });

            $assignment = $debt->assignments()->create([
                'collector_id' => $collector->getKey(),
                'assigned_by' => $assignedBy?->getKey() ?? Auth::id(),
                'assigned_at' => $now,
                'notes' => $notes,
            ]);

            if ($activateIfReady && $debt->status === Debt::STATUS_READY_FOR_COLLECTION) {
                $this->transition($debt, Debt::STATUS_ACTIVE_COLLECTION, $assignedBy, 'Ativada ao atribuir cobrador.');
            }

            AuditLogger::log(
                event: 'debt_assigned',
                auditable: $assignment,
                newValues: [
                    'debt_id' => $debt->getKey(),
                    'collector_id' => $collector->getKey(),
                    'assigned_by' => $assignment->assigned_by,
                ],
            );

            return $assignment;
        });
    }

    public function endAssignment(
        Debt $debt,
        ?string $reason = null,
        ?User $actor = null,
    ): ?DebtAssignment {
        return DB::transaction(function () use ($debt, $reason, $actor): ?DebtAssignment {
            $active = $debt->assignments()->active()->lockForUpdate()->first();

            if (! $active) {
                return null;
            }

            $active->update([
                'ended_at' => now(),
                'end_reason' => $reason ?? 'ended',
            ]);

            AuditLogger::log(
                event: 'debt_assignment_ended',
                auditable: $active,
                newValues: [
                    'ended_at' => $active->ended_at?->toIso8601String(),
                    'end_reason' => $active->end_reason,
                ],
                metadata: array_filter([
                    'actor_id' => $actor?->getKey() ?? Auth::id(),
                ]),
            );

            return $active;
        });
    }

    /**
     * Record a collection touchpoint or internal note.
     *
     * Outbound collection contacts are blocked while the debt has an active
     * operational hold (disputed, suspended, or under payment verification) or
     * has not yet entered active collection. Internal notes remain allowed for
     * investigation and are always excluded from school-facing queries.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function logAction(
        Debt $debt,
        array $attributes,
        ?User $actor = null,
    ): CollectionAction {
        $channel = (string) ($attributes['channel'] ?? CollectionAction::CHANNEL_PHONE);
        $outcome = (string) ($attributes['outcome'] ?? 'contacted');
        $isInternal = (bool) ($attributes['is_internal'] ?? false)
            || $channel === CollectionAction::CHANNEL_INTERNAL_NOTE;

        $isOutboundContact = ! $isInternal && $channel !== CollectionAction::CHANNEL_SYSTEM;

        if ($isOutboundContact) {
            if ($debt->isCollectionPaused()) {
                throw new DomainException($this->pauseReasonMessage($debt));
            }

            if (! in_array($debt->status, self::COLLECTION_ACTIVE_STATUSES, true)) {
                throw new DomainException(
                    "Não é possível registrar contato de cobrança enquanto a dívida está no status '{$debt->status}'."
                );
            }
        }

        return DB::transaction(function () use ($debt, $attributes, $actor, $channel, $outcome, $isInternal): CollectionAction {
            $activeAssignmentId = $attributes['debt_assignment_id']
                ?? $debt->activeAssignment()->value('id');

            $action = $debt->collectionActions()->create([
                'debt_assignment_id' => $activeAssignmentId,
                'user_id' => $actor?->getKey() ?? Auth::id(),
                'channel' => $channel,
                'outcome' => $outcome,
                'acted_at' => $attributes['acted_at'] ?? now(),
                'notes' => $attributes['notes'] ?? null,
                'is_internal' => $isInternal,
                'evidence_path' => $attributes['evidence_path'] ?? null,
                'evidence_original_name' => $attributes['evidence_original_name'] ?? null,
            ]);

            // Pause collection automatically when the guardian disputes the debt or reports payment.
            if ($outcome === 'disputed' && ! $debt->is_disputed) {
                $this->placeHold($debt, self::HOLD_DISPUTED, 'Contestação registrada durante atendimento.', $actor);
            } elseif ($outcome === 'reported_paid' && ! $debt->under_payment_verification) {
                $this->placeHold($debt, self::HOLD_PAYMENT_VERIFICATION, 'Pagamento informado pelo responsável — aguardando confirmação.', $actor);
            }

            return $action;
        });
    }

    public function scheduleTask(
        Debt $debt,
        User $assignee,
        string $title,
        CarbonInterface $dueAt,
        ?string $description = null,
        string $priority = 'normal',
        ?CollectionAction $sourceAction = null,
        ?User $creator = null,
    ): CollectionTask {
        return $debt->collectionTasks()->create([
            'assigned_to' => $assignee->getKey(),
            'created_by' => $creator?->getKey() ?? Auth::id(),
            'collection_action_id' => $sourceAction?->getKey(),
            'title' => $title,
            'description' => $description,
            'due_at' => $dueAt,
            'status' => CollectionTask::STATUS_PENDING,
            'priority' => $priority,
        ]);
    }

    public function completeTask(CollectionTask $task, ?User $actor = null): CollectionTask
    {
        $task->update([
            'status' => CollectionTask::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $actor?->getKey() ?? Auth::id(),
        ]);

        return $task->refresh();
    }

    private function resolveHoldColumn(string $holdType): string
    {
        if (! isset(self::HOLD_COLUMNS[$holdType])) {
            throw new InvalidArgumentException("Tipo de retenção inválido: {$holdType}.");
        }

        return self::HOLD_COLUMNS[$holdType];
    }

    private function pauseReasonMessage(Debt $debt): string
    {
        $reasons = [];

        if ($debt->is_disputed) {
            $reasons[] = 'dívida contestada';
        }
        if ($debt->is_suspended) {
            $reasons[] = 'cobrança suspensa';
        }
        if ($debt->under_payment_verification) {
            $reasons[] = 'pagamento em verificação';
        }

        $list = implode(', ', $reasons);

        return "Cobrança pausada ({$list}). Resolva a retenção antes de prosseguir.";
    }
}
