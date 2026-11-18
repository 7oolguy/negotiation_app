<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Debt extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    public const STATUS_IMPORTED = 'imported';
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_READY_FOR_COLLECTION = 'ready_for_collection';
    public const STATUS_ACTIVE_COLLECTION = 'active_collection';
    public const STATUS_NEGOTIATING = 'negotiating';
    public const STATUS_AGREED = 'agreed';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_WRITTEN_OFF = 'written_off';

    /** @var array<string, string> */
    public const STATUSES = [
        self::STATUS_IMPORTED => 'Importada',
        self::STATUS_PENDING_VERIFICATION => 'Aguardando Verificação',
        self::STATUS_READY_FOR_COLLECTION => 'Pronta para Cobrança',
        self::STATUS_ACTIVE_COLLECTION => 'Em Cobrança Ativa',
        self::STATUS_NEGOTIATING => 'Em Negociação',
        self::STATUS_AGREED => 'Com Acordo',
        self::STATUS_SETTLED => 'Quitada',
        self::STATUS_WRITTEN_OFF => 'Baixada / Encerrada',
    ];

    protected $fillable = [
        'school_id',
        'student_id',
        'guardian_id',
        'enrollment_contract_id',
        'external_reference',
        'principal_cents',
        'interest_cents',
        'fine_cents',
        'correction_cents',
        'status',
        'is_disputed',
        'is_suspended',
        'under_payment_verification',
        'notes',
    ];

    protected $casts = [
        'principal_cents' => 'integer',
        'interest_cents' => 'integer',
        'fine_cents' => 'integer',
        'correction_cents' => 'integer',
        'is_disputed' => 'boolean',
        'is_suspended' => 'boolean',
        'under_payment_verification' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function enrollmentContract(): BelongsTo
    {
        return $this->belongsTo(EnrollmentContract::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(DebtInstallment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DebtAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(DebtAssignment::class)->whereNull('ended_at');
    }

    public function collectionActions(): HasMany
    {
        return $this->hasMany(CollectionAction::class);
    }

    public function publicCollectionActions(): HasMany
    {
        return $this->hasMany(CollectionAction::class)->where('is_internal', false);
    }

    public function collectionTasks(): HasMany
    {
        return $this->hasMany(CollectionTask::class);
    }

    public function totalAmountCents(): int
    {
        return (int) $this->principal_cents
            + (int) $this->interest_cents
            + (int) $this->fine_cents
            + (int) $this->correction_cents;
    }

    /**
     * Any active operational hold pauses collection activity.
     */
    public function isCollectionPaused(): bool
    {
        return (bool) ($this->is_disputed || $this->is_suspended || $this->under_payment_verification);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if (! $user->isSchoolScoped()) {
            return $query;
        }

        return $query->whereIn('school_id', $user->schools()->select('schools.id'));
    }

    public function scopeAssignedTo(Builder $query, User $collector): Builder
    {
        return $query->whereHas(
            'activeAssignment',
            fn (Builder $q) => $q->where('collector_id', $collector->getKey()),
        );
    }
}
