<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attendance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'school_id', 'subject_type', 'subject_id', 'date', 'status',
        'check_in_at', 'method', 'match_score', 'bypassed_by', 'reason',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_at' => 'datetime',
        'match_score' => 'float',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function bypassedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bypassed_by');
    }
}
