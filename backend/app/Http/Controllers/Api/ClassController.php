<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Classes CRUD. Tenant-scoped + admin-gated. Deleting a class nulls its students'
 * class_id (FK nullOnDelete), so no students are lost.
 */
class ClassController extends Controller
{
    public function index(): JsonResponse
    {
        $classes = SchoolClass::with('homeroomTeacher.user:id,name')
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $classes]);
    }

    public function store(Request $request): JsonResponse
    {
        $class = SchoolClass::create($this->validated($request));

        return response()->json($this->withRelations($class), 201);
    }

    public function update(Request $request, SchoolClass $class): JsonResponse
    {
        $class->update($this->validated($request, $class));

        return response()->json($this->withRelations($class->fresh()));
    }

    public function destroy(SchoolClass $class): JsonResponse
    {
        $class->delete();

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?SchoolClass $class = null): array
    {
        $schoolId = $request->user()->school_id;

        return $request->validate([
            'name' => [$class ? 'sometimes' : 'required', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:50'],
            // homeroom teacher must belong to the same school (tenant safety).
            'homeroom_teacher_id' => ['nullable', Rule::exists('teachers', 'id')->where('school_id', $schoolId)],
        ]);
    }

    private function withRelations(SchoolClass $class): SchoolClass
    {
        return $class->load('homeroomTeacher.user:id,name')->loadCount('students');
    }
}
