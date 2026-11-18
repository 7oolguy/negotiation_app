<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollectionAction extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    public const CHANNEL_PHONE = 'phone';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_LETTER = 'letter';
    public const CHANNEL_IN_PERSON = 'in_person';
    public const CHANNEL_INTERNAL_NOTE = 'internal_note';
    public const CHANNEL_SYSTEM = 'system';

    /** @var array<string, string> */
    public const CHANNELS = [
        self::CHANNEL_PHONE => 'Telefone',
        self::CHANNEL_WHATSAPP => 'WhatsApp',
        self::CHANNEL_EMAIL => 'E-mail',
        self::CHANNEL_SMS => 'SMS',
        self::CHANNEL_LETTER => 'Carta / Notificação',
        self::CHANNEL_IN_PERSON => 'Presencial',
        self::CHANNEL_INTERNAL_NOTE => 'Nota Interna',
        self::CHANNEL_SYSTEM => 'Sistema',
    ];

    /** @var array<string, string> */
    public const OUTCOMES = [
        'contacted' => 'Contato realizado',
        'no_answer' => 'Sem resposta',
        'wrong_number' => 'Número incorreto',
        'promise_to_pay' => 'Promessa de pagamento',
        'negotiating' => 'Em negociação',
        'disputed' => 'Contestado pelo responsável',
        'reported_paid' => 'Pagamento informado',
        'refused' => 'Recusa de pagamento',
        'message_sent' => 'Mensagem enviada',
        'note' => 'Registro / Nota',
    ];

    protected $fillable = [
        'debt_id',
        'debt_assignment_id',
        'user_id',
        'channel',
        'outcome',
        'acted_at',
        'notes',
        'is_internal',
        'evidence_path',
        'evidence_original_name',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
            'is_internal' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $action): void {
            // Internal notes can never accidentally leak into school-facing views.
            if ($action->channel === self::CHANNEL_INTERNAL_NOTE) {
                $action->is_internal = true;
            }

            if ($action->acted_at === null) {
                $action->acted_at = now();
            }
        });
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DebtAssignment::class, 'debt_assignment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CollectionTask::class);
    }

    public function scopeVisibleToSchool(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternalOnly(Builder $query): Builder
    {
        return $query->where('is_internal', true);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isSchoolScoped()) {
            return $query
                ->visibleToSchool()
                ->whereHas('debt', fn (Builder $q) => $q->forUser($user));
        }

        return $query;
    }
}
