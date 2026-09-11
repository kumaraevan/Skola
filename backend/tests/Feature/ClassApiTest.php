<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassApiTest extends TestCase
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

    private function makeTeacher(School $school, string $name = 'Wali'): Teacher
    {
        $user = User::create([
            'school_id' => $school->id,
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => Role::Teacher,
        ]);

        return Teacher::create(['school_id' => $school->id, 'user_id' => $user->id]);
    }

    public function test_index_lists_own_school_with_homeroom_and_student_count(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $wali = $this->makeTeacher($school, 'Pak Wali');
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'X IPA 1', 'homeroom_teacher_id' => $wali->id]);
        Student::create(['school_id' => $school->id, 'name' => 'S1', 'class_id' => $class->id]);
        Student::create(['school_id' => $school->id, 'name' => 'S2', 'class_id' => $class->id]);
        SchoolClass::create(['school_id' => School::create(['name' => 'Other'])->id, 'name' => 'Foreign Class']);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/classes');

        $res->assertOk()->assertJsonCount(1, 'data');
        $res->assertJsonFragment(['name' => 'X IPA 1', 'students_count' => 2]);
        $res->assertJsonFragment(['name' => 'Pak Wali']); // nested homeroom teacher user
        $res->assertJsonMissing(['name' => 'Foreign Class']);
    }

    public function test_admin_can_create_a_class(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $wali = $this->makeTeacher($school);

        $res = $this->actingAs($admin, 'sanctum')->postJson('/api/classes', [
            'name' => 'XI IPS 2',
            'grade' => '11',
            'homeroom_teacher_id' => $wali->id,
        ]);

        $res->assertCreated()->assertJsonFragment(['name' => 'XI IPS 2']);
        $this->assertDatabaseHas('school_classes', ['name' => 'XI IPS 2', 'school_id' => $school->id]);
    }

    public function test_create_requires_a_name(): void
    {
        [, $admin] = $this->schoolWithAdmin();

        $this->actingAs($admin, 'sanctum')->postJson('/api/classes', ['grade' => '10'])->assertStatus(422);
    }

    public function test_homeroom_teacher_must_belong_to_the_same_school(): void
    {
        [, $admin] = $this->schoolWithAdmin();
        $foreignTeacher = $this->makeTeacher(School::create(['name' => 'Other']));

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/classes', ['name' => 'X', 'homeroom_teacher_id' => $foreignTeacher->id])
            ->assertStatus(422);
    }

    public function test_admin_can_update_a_class(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Old']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/classes/{$class->id}", ['name' => 'New'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'New']);

        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'name' => 'New']);
    }

    public function test_admin_cannot_touch_another_schools_class(): void
    {
        [, $admin] = $this->schoolWithAdmin();
        $foreign = SchoolClass::create(['school_id' => School::create(['name' => 'Other'])->id, 'name' => 'Foreign']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/classes/{$foreign->id}", ['name' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_delete_class_nulls_its_students_class_id(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Gone']);
        $student = Student::create(['school_id' => $school->id, 'name' => 'S', 'class_id' => $class->id]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/classes/{$class->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('school_classes', ['id' => $class->id]);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'class_id' => null]);
    }

    public function test_teacher_role_is_forbidden(): void
    {
        [$school] = $this->schoolWithAdmin();
        $t = $this->makeTeacher($school);

        $this->actingAs($t->user, 'sanctum')->getJson('/api/classes')->assertForbidden();
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/classes')->assertUnauthorized();
    }
}
