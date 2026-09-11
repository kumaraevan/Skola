<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard KPI counts (v1: student-focused). Tenant-scoped via BelongsToTenant.
 */
class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $total = Student::count();
        $present = Attendance::whereDate('date', now()->toDateString())
            ->where('subject_type', Student::class)
            ->where('status', 'present')
            ->count();
        $pending = Student::where('enrolment_status', 'pending_enrolment')->count();

        return response()->json([
            'total_students' => $total,
            'present_today' => $present,
            'absent_today' => $total - $present,
            'pending_enrolment' => $pending,
        ]);
    }
}
