<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DebtAssignment extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'debt_id',
        'collector_id',
        'assigned_by',
        'assigned_at',
        'ended_at',
        'end_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function collectionActions(): HasMany
    {
        return $this->hasMany(CollectionAction::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeForCollector(Builder $query, User $collector): Builder
    {
        return $query->where('collector_id', $collector->getKey());
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isSchoolScoped()) {
            return $query->whereHas('debt', fn (Builder $q) => $q->forUser($user));
        }

        return $query;
    }
}
