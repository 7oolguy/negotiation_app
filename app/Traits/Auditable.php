<?php

namespace App\Traits;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @method static void created(\Closure $callback)
 * @method static void updated(\Closure $callback)
 * @method static void deleted(\Closure $callback)
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            AuditLogger::log(
                event: 'created',
                auditable: $model,
                newValues: Arr::except($model->getAttributes(), ['updated_at', 'created_at']),
            );
        });

        static::updated(function (Model $model): void {
            
            $dirty = Arr::except($model->getDirty(), ['updated_at']);
            
            if ($dirty === []) {
                return;
            }

            $old = Arr::only($model->getOriginal(), array_keys($dirty));

            AuditLogger::log(
                event: 'updated',
                auditable: $model,
                oldValues: $old,
                newValues: $dirty
            );
        });

        static::deleted(function (Model $model): void {
            AuditLogger::log(
                event: 'deleted',
                auditable: $model,
                oldValues: Arr::except($model->getAttributes(), ['updated_at', 'created_at'])
            );
        });
    }
}
