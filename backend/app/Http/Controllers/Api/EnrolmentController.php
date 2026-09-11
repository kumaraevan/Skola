<?php

namespace App\Http\Controllers\Api;

use App\Enums\EnrolmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\FaceEmbedding;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Face enrolment (PLANNING §4.6). Account exists first; this records the initial
 * (or re-enrolment) face embedding. Biometric consent MUST be active first (§8).
 */
class EnrolmentController extends Controller
{
    private const SUBJECTS = ['student' => Student::class, 'teacher' => Teacher::class];

    /** Enrolment overview: each subject with enrolment status + whether biometric consent is active. */
    public function index(Request $request): JsonResponse
    {
        $type = $request->string('type')->toString() === 'teacher' ? 'teacher' : 'student';
        $model = self::SUBJECTS[$type];

        $consented = Consent::where('subject_type', $model)
            ->whereNotNull('granted_at')
            ->whereNull('revoked_at')
            ->pluck('subject_id')
            ->flip();

        $subjects = $type === 'teacher'
            ? Teacher::with('user:id,name')->get()->map(fn ($t) => [
                'subject_id' => $t->id,
                'name' => $t->user?->name,
                'enrolment_status' => $t->enrolment_status,
            ])->sortBy('name')->values()
            : Student::orderBy('name')->get()->map(fn ($s) => [
                'subject_id' => $s->id,
                'name' => $s->name,
                'enrolment_status' => $s->enrolment_status,
            ]);

        $data = $subjects->map(fn (array $row) => [
            ...$row,
            'has_consent' => $consented->has($row['subject_id']),
        ])->values();

        return response()->json(['type' => $type, 'data' => $data]);
    }

    /** Record biometric consent for a subject (granted by parent/guardian or the subject). */
    public function consent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(array_keys(self::SUBJECTS))],
            'subject_id' => ['required', 'integer'],
        ]);

        $subject = self::SUBJECTS[$data['subject_type']]::findOrFail($data['subject_id']);

        $consent = Consent::create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'type' => 'biometric',
            'granted_by' => $request->user()->id,
            'granted_at' => now(),
        ]);

        AuditLog::record('consent.granted', $subject);

        return response()->json($consent, 201);
    }

    /**
     * Store an embedding produced on-device (initial scan or re-enrolment).
     * Re-enrolment deactivates prior embeddings for the subject.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(array_keys(self::SUBJECTS))],
            'subject_id' => ['required', 'integer'],
            'embedding' => ['required', 'array'],
            'model_version' => ['nullable', 'string'],
        ]);

        $subject = self::SUBJECTS[$data['subject_type']]::findOrFail($data['subject_id']);

        $hasConsent = Consent::where('subject_type', $subject::class)
            ->where('subject_id', $subject->id)
            ->whereNotNull('granted_at')
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasConsent) {
            return response()->json(['message' => 'Biometric consent required before enrolment.'], 422);
        }

        // Re-enrolment: keep old rows for audit but deactivate them.
        FaceEmbedding::where('subject_type', $subject::class)
            ->where('subject_id', $subject->id)
            ->update(['active' => false]);

        $embedding = FaceEmbedding::create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'embedding' => $data['embedding'],
            'model_version' => $data['model_version'] ?? null,
            'active' => true,
            'enrolled_by' => $request->user()->id,
            'enrolled_at' => now(),
        ]);

        $subject->update(['enrolment_status' => EnrolmentStatus::Enrolled]);
        AuditLog::record('face.enrolled', $subject, ['embedding_id' => $embedding->id]);

        return response()->json($embedding->only(['id', 'active', 'enrolled_at']), 201);
    }
}
