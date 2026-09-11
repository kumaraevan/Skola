<?php

namespace App\Models;

use App\Enums\EnrolmentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Student extends Model
{
    use BelongsToTenant;

    protected $fillable = ['school_id', 'user_id', 'nis', 'name', 'class_id', 'enrolment_status', 'status'];

    protected $casts = ['enrolment_status' => EnrolmentStatus::class];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_guardian', 'student_id', 'guardian_user_id')
            ->withPivot('relation')
            ->withTimestamps();
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
