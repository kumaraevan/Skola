<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Teachers CRUD. A teacher is a login (User, role=teacher) plus a Teacher profile,
 * so create/update/delete manage both in one transaction. Tenant-scoped + admin-gated.
 */
class TeacherController extends Controller
{
    public function index(): JsonResponse
    {
        $teachers = Teacher::with('user:id,name,email')
            ->join('users', 'users.id', '=', 'teachers.user_id')
            ->orderBy('users.name')
            ->select('teachers.*')
            ->get();

        return response()->json(['data' => $teachers]);
    }

    public function store(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'nip' => ['nullable', 'string', 'max:50'],
        ]);

        $teacher = DB::transaction(function () use ($data, $schoolId) {
            $user = User::create([
                'school_id' => $schoolId,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => Role::Teacher,
            ]);

            return Teacher::create([
                'school_id' => $schoolId,
                'user_id' => $user->id,
                'nip' => $data['nip'] ?? null,
            ]);
        });

        return response()->json($teacher->load('user:id,name,email'), 201);
    }

    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teacher->user_id)],
            'nip' => ['nullable', 'string', 'max:50'],
            'enrolment_status' => ['nullable', Rule::in(['pending_enrolment', 'enrolled', 'inactive'])],
        ]);

        DB::transaction(function () use ($teacher, $data) {
            $teacher->user->fill(array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
            ]))->save();

            $teacher->update(array_filter([
                'nip' => $data['nip'] ?? null,
                'enrolment_status' => $data['enrolment_status'] ?? null,
            ], fn ($v) => $v !== null));
        });

        return response()->json($teacher->fresh()->load('user:id,name,email'));
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        // Deleting the user cascades the teacher row (FK cascadeOnDelete).
        $teacher->user->delete();

        return response()->json(status: 204);
    }
}
