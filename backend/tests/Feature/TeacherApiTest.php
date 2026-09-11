<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherApiTest extends TestCase
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

    private function makeTeacher(School $school, string $name = 'Guru'): Teacher
    {
        $user = User::create([
            'school_id' => $school->id,
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => Role::Teacher,
        ]);

        return Teacher::create(['school_id' => $school->id, 'user_id' => $user->id, 'nip' => '123']);
    }

    public function test_index_lists_only_the_admins_own_school_with_user(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $this->makeTeacher($school, 'Budi');
        $this->makeTeacher(School::create(['name' => 'Other']), 'Foreign');

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/teachers');

        $res->assertOk()->assertJsonCount(1, 'data');
        $res->assertJsonFragment(['name' => 'Budi']);
        $res->assertJsonMissing(['name' => 'Foreign']);
    }

    public function test_admin_can_create_a_teacher_with_login(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();

        $res = $this->actingAs($admin, 'sanctum')->postJson('/api/teachers', [
            'name' => 'Siti Aminah',
            'email' => 'siti@skola.test',
            'password' => 'secret123',
            'nip' => '198501012010012002',
        ]);

        $res->assertCreated()->assertJsonFragment(['nip' => '198501012010012002']);
        $this->assertDatabaseHas('users', [
            'email' => 'siti@skola.test',
            'role' => Role::Teacher->value,
            'school_id' => $school->id,
        ]);
        $this->assertDatabaseHas('teachers', ['school_id' => $school->id, 'nip' => '198501012010012002']);
    }

    public function test_create_requires_name_email_password(): void
    {
        [, $admin] = $this->schoolWithAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/teachers', ['nip' => '1'])
            ->assertStatus(422);
    }

    public function test_create_rejects_duplicate_email(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $existing = $this->makeTeacher($school);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/teachers', [
                'name' => 'Dup',
                'email' => $existing->user->email,
                'password' => 'secret123',
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_update_teacher_name_and_nip(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $teacher = $this->makeTeacher($school, 'Old Name');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/teachers/{$teacher->id}", ['name' => 'New Name', 'nip' => '999'])
            ->assertOk()
            ->assertJsonFragment(['nip' => '999']);

        $this->assertDatabaseHas('users', ['id' => $teacher->user_id, 'name' => 'New Name']);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'nip' => '999']);
    }

    public function test_admin_cannot_touch_another_schools_teacher(): void
    {
        [, $admin] = $this->schoolWithAdmin();
        $foreign = $this->makeTeacher(School::create(['name' => 'Other']), 'Foreign');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/teachers/{$foreign->id}", ['name' => 'Hacked'])
            ->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $foreign->user_id, 'name' => 'Foreign']);
    }

    public function test_delete_removes_teacher_and_its_login(): void
    {
        [$school, $admin] = $this->schoolWithAdmin();
        $teacher = $this->makeTeacher($school);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/teachers/{$teacher->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseMissing('users', ['id' => $teacher->user_id]);
    }

    public function test_teacher_role_is_forbidden(): void
    {
        [$school] = $this->schoolWithAdmin();
        $t = $this->makeTeacher($school);

        $this->actingAs($t->user, 'sanctum')->getJson('/api/teachers')->assertForbidden();
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/teachers')->assertUnauthorized();
    }
}
