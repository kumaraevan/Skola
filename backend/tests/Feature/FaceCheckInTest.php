<?php

namespace Tests\Feature;

use App\Enums\EnrolmentStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\FaceEmbedding;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceCheckInTest extends TestCase
{
    use RefreshDatabase;

    /** A device/kiosk account (a user with a school). */
    private function kiosk(School $school): User
    {
        return User::create([
            'school_id' => $school->id,
            'name' => 'Gate Kiosk',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => Role::Teacher,
        ]);
    }

    private function enrol(School $school, string $name, array $embedding, bool $active = true): Student
    {
        $student = Student::create(['school_id' => $school->id, 'name' => $name, 'enrolment_status' => EnrolmentStatus::Enrolled]);
        FaceEmbedding::create([
            'school_id' => $school->id,
            'subject_type' => Student::class,
            'subject_id' => $student->id,
            'embedding' => $embedding,
            'active' => $active,
            'enrolled_at' => now(),
        ]);

        return $student;
    }

    public function test_matching_embedding_checks_the_student_in(): void
    {
        $school = School::create(['name' => 'A']);
        $student = $this->enrol($school, 'Ani', [1.0, 0.0, 0.0]);

        $res = $this->actingAs($this->kiosk($school), 'sanctum')
            ->postJson('/api/attendance/check-in', ['embedding' => [1.0, 0.0, 0.0]]);

        $res->assertCreated()->assertJson(['matched' => true, 'already' => false, 'name' => 'Ani', 'status' => 'present']);
        $this->assertDatabaseHas('attendances', [
            'subject_id' => $student->id,
            'subject_type' => Student::class,
            'status' => 'present',
            'method' => 'face',
        ]);
    }

    public function test_below_threshold_is_not_recognized(): void
    {
        $school = School::create(['name' => 'A']);
        $this->enrol($school, 'Ani', [1.0, 0.0, 0.0]);

        $res = $this->actingAs($this->kiosk($school), 'sanctum')
            ->postJson('/api/attendance/check-in', ['embedding' => [0.0, 1.0, 0.0]]); // orthogonal

        $res->assertStatus(422)->assertJson(['matched' => false]);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_picks_the_closest_enrolled_face(): void
    {
        $school = School::create(['name' => 'A']);
        $this->enrol($school, 'Ani', [1.0, 0.0]);
        $bagus = $this->enrol($school, 'Bagus', [0.0, 1.0]);

        $res = $this->actingAs($this->kiosk($school), 'sanctum')
            ->postJson('/api/attendance/check-in', ['embedding' => [0.05, 0.99]]);

        $res->assertCreated()->assertJson(['matched' => true, 'name' => 'Bagus']);
        $this->assertDatabaseHas('attendances', ['subject_id' => $bagus->id, 'method' => 'face']);
    }

    public function test_second_check_in_same_day_is_idempotent(): void
    {
        $school = School::create(['name' => 'A']);
        $this->enrol($school, 'Ani', [1.0, 0.0, 0.0]);
        $kiosk = $this->kiosk($school);

        $this->actingAs($kiosk, 'sanctum')->postJson('/api/attendance/check-in', ['embedding' => [1.0, 0.0, 0.0]])->assertCreated();
        $again = $this->actingAs($kiosk, 'sanctum')->postJson('/api/attendance/check-in', ['embedding' => [1.0, 0.0, 0.0]]);

        $again->assertOk()->assertJson(['matched' => true, 'already' => true]);
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_matching_is_scoped_to_the_kiosks_school(): void
    {
        $schoolA = School::create(['name' => 'A']);
        $schoolB = School::create(['name' => 'B']);
        $this->enrol($schoolB, 'Foreign', [1.0, 0.0, 0.0]); // enrolled in another school

        $res = $this->actingAs($this->kiosk($schoolA), 'sanctum')
            ->postJson('/api/attendance/check-in', ['embedding' => [1.0, 0.0, 0.0]]);

        $res->assertStatus(422)->assertJson(['matched' => false]);
    }

    public function test_inactive_embeddings_are_ignored(): void
    {
        $school = School::create(['name' => 'A']);
        $this->enrol($school, 'Ani', [1.0, 0.0, 0.0], active: false);

        $this->actingAs($this->kiosk($school), 'sanctum')
            ->postJson('/api/attendance/check-in', ['embedding' => [1.0, 0.0, 0.0]])
            ->assertStatus(422);
    }

    public function test_embedding_is_required(): void
    {
        $school = School::create(['name' => 'A']);
        $this->actingAs($this->kiosk($school), 'sanctum')
            ->postJson('/api/attendance/check-in', [])
            ->assertStatus(422);
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->postJson('/api/attendance/check-in', ['embedding' => [1.0]])->assertUnauthorized();
    }
}
