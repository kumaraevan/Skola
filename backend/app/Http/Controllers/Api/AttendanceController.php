<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    private const SUBJECTS = ['student' => Student::class, 'teacher' => Teacher::class];

    /**
     * Attendance roster for a date. Every subject (student or teacher) is listed;
     * a subject with no record that day is 'absent' (v1 presence semantics).
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))->toDateString()
            : now()->toDateString();
        $type = $request->string('type')->toString() === 'teacher' ? 'teacher' : 'student';
        $model = self::SUBJECTS[$type];

        $records = Attendance::whereDate('date', $date)
            ->where('subject_type', $model)
            ->get()
            ->keyBy('subject_id');

        $subjects = $type === 'teacher'
            ? Teacher::with('user:id,name')->get()->map(fn ($t) => [
                'subject_id' => $t->id,
                'name' => $t->user?->name,
                'class_name' => null,
            ])->sortBy('name')->values()
            : Student::with('schoolClass:id,name')->orderBy('name')->get()->map(fn ($s) => [
                'subject_id' => $s->id,
                'name' => $s->name,
                'class_name' => $s->schoolClass?->name,
            ]);

        $data = $subjects->map(function (array $row) use ($records) {
            $rec = $records->get($row['subject_id']);

            return [
                ...$row,
                'status' => $rec->status ?? 'absent',
                'method' => $rec->method ?? null,
                'check_in_at' => $rec->check_in_at ?? null,
            ];
        })->values();

        return response()->json(['date' => $date, 'type' => $type, 'data' => $data]);
    }

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
