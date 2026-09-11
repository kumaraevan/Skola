<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FaceEmbedding extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'school_id', 'subject_type', 'subject_id', 'embedding',
        'model_version', 'active', 'enrolled_by', 'enrolled_at',
    ];

    protected $casts = [
        'embedding' => 'array', // JSON now; pgvector in production
        'active' => 'boolean',
        'enrolled_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
