<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Students CRUD. All queries are tenant-scoped by BelongsToTenant, so a student
 * from another school is invisible (route-model binding returns 404, never 200).
 * Routes are gated to super_admin + school_admin (see routes/api.php).
 */
class StudentController extends Controller
{
    public function index(): JsonResponse
    {
        $students = Student::with('schoolClass:id,name')->orderBy('name')->get();

        return response()->json(['data' => $students]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $student = Student::create($data);

        return response()->json($student->load('schoolClass:id,name'), 201);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $student->update($this->validated($request, $student));

        return response()->json($student->load('schoolClass:id,name'));
    }

    public function destroy(Student $student): JsonResponse
    {
        $student->delete();

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        $schoolId = $request->user()->school_id;

        return $request->validate([
            'name' => [$student ? 'sometimes' : 'required', 'string', 'max:255'],
            'nis' => ['nullable', 'string', 'max:50'],
            // class_id must belong to the same school (tenant safety).
            'class_id' => ['nullable', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'enrolment_status' => ['nullable', Rule::in(['pending_enrolment', 'enrolled', 'inactive'])],
            'status' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
