<?php

namespace App\Http\Controllers\Api;

use App\Contracts\WhatsAppGateway;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\FaceEmbedding;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\FaceMatcher;
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
     * Kiosk check-in (1:N). The tablet sends an on-device embedding (after passing its
     * own liveness challenge — the server cannot verify liveness, PLANNING §4.3); the
     * server matches against the school's active embeddings and records attendance.
     */
    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'embedding' => ['required', 'array', 'min:1'],
            'embedding.*' => ['numeric'],
        ]);

        $user = $request->user();
        abort_unless($user->school_id, 403, 'Kiosk must belong to a school.');

        // Tenant scope limits embeddings to the kiosk's school.
        $matcher = new FaceMatcher((float) config('face.match_threshold'));
        $match = $matcher->best($data['embedding'], FaceEmbedding::where('active', true)->get());

        if ($match === null) {
            return response()->json(['matched' => false, 'message' => 'Face not recognized.'], 422);
        }

        $embedding = $match['embedding'];
        $subject = $embedding->subject;
        $name = $subject instanceof Teacher ? $subject->user?->name : $subject->name;

        // One record per subject per day: a repeat scan is a no-op, not a duplicate.
        $existing = Attendance::whereDate('date', now()->toDateString())
            ->where('subject_type', $embedding->subject_type)
            ->where('subject_id', $embedding->subject_id)
            ->first();

        if ($existing) {
            return response()->json([
                'matched' => true,
                'already' => true,
                'name' => $name,
                'status' => $existing->status,
                'check_in_at' => $existing->check_in_at,
            ]);
        }

        $attendance = Attendance::create([
            'subject_type' => $embedding->subject_type,
            'subject_id' => $embedding->subject_id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'method' => 'face',
            'match_score' => round($match['score'], 4),
            'check_in_at' => now(),
        ]);

        $this->notifyArrival($subject, $name, $attendance);

        return response()->json([
            'matched' => true,
            'already' => false,
            'name' => $name,
            'status' => 'present',
            'method' => 'face',
            'match_score' => $attendance->match_score,
            'check_in_at' => $attendance->check_in_at,
        ], 201);
    }

    /** Fire the WhatsApp arrival notice. Never let a gateway failure lose the attendance record. */
    private function notifyArrival(object $subject, ?string $name, Attendance $attendance): void
    {
        $phone = $subject instanceof Teacher
            ? $subject->user?->phone
            : $subject->guardians()->first()?->phone;

        if (! $phone) {
            return;
        }

        try {
            $time = $attendance->check_in_at->format('H:i');
            app(WhatsAppGateway::class)->send($phone, "Ananda {$name} telah hadir di sekolah pukul {$time} WIB.");
        } catch (\Throwable $e) {
            report($e);
        }
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
