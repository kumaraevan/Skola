<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    private const SUBJECTS = ['student' => Student::class, 'teacher' => Teacher::class];

    /**
     * Kiosk check-in: tablet sends an on-device embedding; server matches it against
     * enrolled embeddings and records attendance (PLANNING §4.2).
     *
     * TODO (v1 feature): implement vector match + threshold. On Postgres use pgvector;
     * for now this is the seam, not the algorithm.
     */
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'embedding' => ['required', 'array'],
        ]);

        return response()->json([
            'message' => 'Face matching not implemented yet (frame stub). See PLANNING §4.2.',
        ], 501);
    }

    /**
     * Tier-1 gate bypass (PLANNING §4.4): the gate-duty teacher marks a person present
     * when their scan fails. Attributable + audited; never anonymous.
     */
    public function bypass(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(array_keys(self::SUBJECTS))],
            'subject_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $subject = self::SUBJECTS[$data['subject_type']]::findOrFail($data['subject_id']);

        $attendance = Attendance::updateOrCreate(
            [
                'subject_type' => $subject::class,
                'subject_id' => $subject->id,
                'date' => now()->toDateString(),
            ],
            [
                'status' => 'present',
                'check_in_at' => now(),
                'method' => 'bypass',
                'bypassed_by' => $request->user()->id,
                'reason' => $data['reason'],
            ],
        );

        AuditLog::record('attendance.bypass', $subject, ['reason' => $data['reason']]);

        return response()->json($attendance, 201);
    }
}
