<?php

namespace Tests\Feature;

use App\Enums\EnrolmentStatus;
use App\Enums\Role;
use App\Models\Consent;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrolmentIndexTest extends TestCase
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

    public function test_lists_students_with_status_and_consent_flag(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        Student::create(['school_id' => $school->id, 'name' => 'NoConsent', 'enrolment_status' => EnrolmentStatus::Pending]);
        $withConsent = Student::create(['school_id' => $school->id, 'name' => 'HasConsent', 'enrolment_status' => EnrolmentStatus::Enrolled]);
        Consent::create([
            'school_id' => $school->id,
            'subject_type' => Student::class,
            'subject_id' => $withConsent->id,
            'type' => 'biometric',
            'granted_at' => now(),
        ]);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/enrolment');

        $res->assertOk()->assertJsonCount(2, 'data');
        $res->assertJsonFragment(['name' => 'NoConsent', 'enrolment_status' => 'pending_enrolment', 'has_consent' => false]);
        $res->assertJsonFragment(['name' => 'HasConsent', 'enrolment_status' => 'enrolled', 'has_consent' => true]);
    }

    public function test_revoked_consent_does_not_count(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $student = Student::create(['school_id' => $school->id, 'name' => 'Revoked']);
        Consent::create([
            'school_id' => $school->id,
            'subject_type' => Student::class,
            'subject_id' => $student->id,
            'type' => 'biometric',
            'granted_at' => now()->subDay(),
            'revoked_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/enrolment')
            ->assertJsonFragment(['name' => 'Revoked', 'has_consent' => false]);
    }

    public function test_is_scoped_to_own_school(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        Student::create(['school_id' => $school->id, 'name' => 'Mine']);
        Student::create(['school_id' => School::create(['name' => 'Other'])->id, 'name' => 'Theirs']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/enrolment')
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Mine']);
    }

    public function test_type_teacher_lists_teachers(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $user = User::create([
            'school_id' => $school->id, 'name' => 'Budi', 'email' => fake()->unique()->safeEmail(),
            'password' => 'password', 'role' => Role::Teacher,
        ]);
        Teacher::create(['school_id' => $school->id, 'user_id' => $user->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/enrolment?type=teacher')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Budi', 'has_consent' => false]);
    }

    public function test_teacher_role_is_forbidden(): void
    {
        [$school] = $this->schoolWithAdmin();
        $user = User::create([
            'school_id' => $school->id, 'name' => 'Guru', 'email' => fake()->unique()->safeEmail(),
            'password' => 'password', 'role' => Role::Teacher,
        ]);
        Teacher::create(['school_id' => $school->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/enrolment')->assertForbidden();
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/enrolment')->assertUnauthorized();
    }
}
