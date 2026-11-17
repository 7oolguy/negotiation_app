<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function log(
        string $event, ?Model $auditable = null, ?array $oldValues = null, ?array $newValues = null, array $metadata = [],
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues ?? null,
            'new_values' => $newValues ?? null,
            'metadata' => array_filter([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                ...$metadata,
            ]),
            'created_at' => now(),
        ]);
    }

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
}
