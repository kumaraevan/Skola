<?php

namespace Tests\Feature;

use App\Enums\EnrolmentStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
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

    public function test_stats_counts_students_present_absent_and_pending(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $present = Student::create(['school_id' => $school->id, 'name' => 'A', 'enrolment_status' => EnrolmentStatus::Enrolled]);
        Student::create(['school_id' => $school->id, 'name' => 'B', 'enrolment_status' => EnrolmentStatus::Enrolled]);
        Student::create(['school_id' => $school->id, 'name' => 'C', 'enrolment_status' => EnrolmentStatus::Pending]);
        Attendance::create([
            'school_id' => $school->id,
            'subject_type' => Student::class,
            'subject_id' => $present->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'method' => 'bypass',
            'check_in_at' => now(),
        ]);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/stats');

        $res->assertOk()->assertJson([
            'total_students' => 3,
            'present_today' => 1,
            'absent_today' => 2,
            'pending_enrolment' => 1,
        ]);
    }

    public function test_stats_are_scoped_to_own_school(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        Student::create(['school_id' => $school->id, 'name' => 'Mine']);
        Student::create(['school_id' => School::create(['name' => 'Other'])->id, 'name' => 'Theirs']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonFragment(['total_students' => 1]);
    }

    public function test_teacher_may_view_stats(): void
    {
        [$school] = $this->schoolWithAdmin();
        $teacher = User::create([
            'school_id' => $school->id, 'name' => 'Guru', 'email' => fake()->unique()->safeEmail(),
            'password' => 'password', 'role' => Role::Teacher,
        ]);

        $this->actingAs($teacher, 'sanctum')->getJson('/api/dashboard/stats')->assertOk();
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/dashboard/stats')->assertUnauthorized();
    }
}
