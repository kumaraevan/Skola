<?php

namespace App\Models\Concerns;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-tenant scoping. Any model using this trait is automatically:
 *   - filtered to the authenticated user's school_id on every query, and
 *   - stamped with that school_id on create.
 *
 * A super admin (school_id === null) bypasses the scope and sees all tenants.
 *
 * ponytail: scoping keys off the authenticated user only. Background jobs / console
 * commands run unscoped (no auth user) — pass school_id explicitly there.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $user = auth()->user();
            if ($user && $user->school_id) {
                $builder->where($builder->getModel()->getTable() . '.school_id', $user->school_id);
            }
        });

        static::creating(function (Model $model) {
            $user = auth()->user();
            if ($user && $user->school_id && empty($model->school_id)) {
                $model->school_id = $user->school_id;
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
