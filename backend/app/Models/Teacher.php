<?php

namespace App\Models;

use App\Enums\EnrolmentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Teacher extends Model
{
    use BelongsToTenant;

    protected $fillable = ['school_id', 'user_id', 'nip', 'enrolment_status'];

    protected $casts = ['enrolment_status' => EnrolmentStatus::class];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function embeddings(): MorphMany
    {
        return $this->morphMany(FaceEmbedding::class, 'subject');
    }

    public function attendances(): MorphMany
    {
        return $this->morphMany(Attendance::class, 'subject');
    }
}
