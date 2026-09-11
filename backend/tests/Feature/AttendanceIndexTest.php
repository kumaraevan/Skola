<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceIndexTest extends TestCase
{
    use RefreshDatabase;

    private function schoolWithAdmin(): array
    {
        $school = School::create(['name' => 'School ' . fake()->unique()->word()]);
        $admin = User::create([
            'school_id' => $school->id,
            'name' => 'Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => Role::SchoolAdmin,
        ]);

        return [$school, $admin];
    }

    public function test_roster_marks_students_without_a_record_absent(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        Student::create(['school_id' => $school->id, 'name' => 'Ani']);
        Student::create(['school_id' => $school->id, 'name' => 'Bagus']);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/attendance?date=' . now()->toDateString());

        $res->assertOk()->assertJsonCount(2, 'data');
        $res->assertJsonFragment(['name' => 'Ani', 'status' => 'absent']);
    }

    public function test_student_with_a_record_shows_present(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $student = Student::create(['school_id' => $school->id, 'name' => 'Citra']);
        Attendance::create([
            'school_id' => $school->id,
            'subject_type' => Student::class,
            'subject_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'method' => 'bypass',
            'check_in_at' => now(),
        ]);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/attendance?date=' . now()->toDateString());

        $res->assertOk()->assertJsonFragment(['name' => 'Citra', 'status' => 'present', 'method' => 'bypass']);
    }

    public function test_roster_is_scoped_to_own_school(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        Student::create(['school_id' => $school->id, 'name' => 'Mine']);
        Student::create(['school_id' => School::create(['name' => 'Other'])->id, 'name' => 'Theirs']);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/attendance');

        $res->assertOk()->assertJsonCount(1, 'data')->assertJsonFragment(['name' => 'Mine']);
        $res->assertJsonMissing(['name' => 'Theirs']);
    }

    public function test_type_teacher_returns_teacher_roster(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $user = User::create([
            'school_id' => $school->id, 'name' => 'Budi', 'email' => fake()->unique()->safeEmail(),
            'password' => 'password', 'role' => Role::Teacher,
        ]);
        Teacher::create(['school_id' => $school->id, 'user_id' => $user->id]);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/attendance?type=teacher');

        $res->assertOk()->assertJsonFragment(['name' => 'Budi', 'status' => 'absent']);
    }

    public function test_teacher_role_may_view_attendance(): void
    {
        [$school] = $this->schoolWithAdmin();
        $user = User::create([
            'school_id' => $school->id, 'name' => 'Guru', 'email' => fake()->unique()->safeEmail(),
            'password' => 'password', 'role' => Role::Teacher,
        ]);
        Teacher::create(['school_id' => $school->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/attendance')->assertOk();
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/attendance')->assertUnauthorized();
    }
}
