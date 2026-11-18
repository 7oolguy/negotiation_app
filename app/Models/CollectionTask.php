<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionTask extends Model
{
    use HasFactory, Auditable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_COMPLETED => 'Concluída',
        self::STATUS_CANCELLED => 'Cancelada',
    ];

    /** @var array<string, string> */
    public const PRIORITIES = [
        'low' => 'Baixa',
        'normal' => 'Normal',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ];

    protected $fillable = [
        'debt_id',
        'assigned_to',
        'created_by',
        'collection_action_id',
        'title',
        'description',
        'due_at',
        'status',
        'priority',
        'completed_at',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function collectionAction(): BelongsTo
    {
        return $this->belongsTo(CollectionAction::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_at !== null && $this->due_at->isPast();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDueTodayOrOverdue(Builder $query): Builder
    {
        return $query->pending()->where('due_at', '<=', now()->endOfDay());
    }

    public function scopeForAssignee(Builder $query, User $user): Builder
    {
        return $query->where('assigned_to', $user->getKey());
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isSchoolScoped()) {
            return $query->whereHas('debt', fn (Builder $q) => $q->forUser($user));
        }

        if ($user->isCollectorOnly()) {
            return $query->forAssignee($user);
        }

        return $query;
    }
}
