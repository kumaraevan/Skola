<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'school_id', 'actor_user_id', 'action', 'target_type', 'target_id', 'detail',
    ];

    protected $casts = ['detail' => 'array'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** Convenience recorder used across the app for audited actions. */
    public static function record(string $action, ?Model $target = null, array $detail = []): self
    {
        $user = auth()->user();

        return self::create([
            'school_id' => $user?->school_id,
            'actor_user_id' => $user?->id,
            'action' => $action,
            'target_type' => $target ? $target::class : null,
            'target_id' => $target?->getKey(),
            'detail' => $detail ?: null,
        ]);
    }
}
