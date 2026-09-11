<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentApiTest extends TestCase
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

    public function test_index_lists_only_the_admins_own_school(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        Student::create(['school_id' => $school->id, 'name' => 'Ani']);
        $other = School::create(['name' => 'Other']);
        Student::create(['school_id' => $other->id, 'name' => 'Bob']);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/students');

        $res->assertOk()->assertJsonCount(1, 'data');
        $res->assertJsonFragment(['name' => 'Ani']);
        $res->assertJsonMissing(['name' => 'Bob']);
    }

    public function test_admin_can_create_a_student(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();

        $res = $this->actingAs($admin, 'sanctum')->postJson('/api/students', [
            'name' => 'Citra Dewi',
            'nis' => '20240001',
        ]);

        $res->assertCreated()->assertJsonFragment(['name' => 'Citra Dewi']);
        $this->assertDatabaseHas('students', ['name' => 'Citra Dewi', 'school_id' => $school->id]);
    }

    public function test_create_requires_a_name(): void
    {
        [, $admin] = $this->schoolWithAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/students', ['nis' => '123'])
            ->assertStatus(422);
    }

    public function test_admin_can_update_a_student(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $student = Student::create(['school_id' => $school->id, 'name' => 'Old']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/students/{$student->id}", ['name' => 'New'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'New']);

        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => 'New']);
    }

    public function test_admin_cannot_touch_another_schools_student(): void
    {
        [, $admin] = $this->schoolWithAdmin();
        $other = School::create(['name' => 'Other']);
        $foreign = Student::create(['school_id' => $other->id, 'name' => 'Foreign']);

        // Tenant scope hides it -> 404, never 200.
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/students/{$foreign->id}", ['name' => 'Hacked'])
            ->assertNotFound();
        $this->assertDatabaseHas('students', ['id' => $foreign->id, 'name' => 'Foreign']);
    }

    public function test_admin_can_delete_a_student(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $student = Student::create(['school_id' => $school->id, 'name' => 'Gone']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/students/{$student->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_teacher_is_forbidden(): void
    {
        [$school] = $this->schoolWithAdmin();
        $teacher = User::create([
            'school_id' => $school->id,
            'name' => 'Teacher',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => Role::Teacher,
        ]);

        $this->actingAs($teacher, 'sanctum')->getJson('/api/students')->assertForbidden();
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/students')->assertUnauthorized();
    }
}
