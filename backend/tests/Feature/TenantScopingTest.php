<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(School $school): User
    {
        return User::create([
            'school_id' => $school->id,
            'name' => 'Admin',
            'email' => 'admin' . $school->id . '@test.local',
            'password' => 'secret123',
            'role' => Role::SchoolAdmin,
        ]);
    }

    public function test_queries_are_scoped_to_the_users_school(): void
    {
        $a = School::create(['name' => 'A']);
        $b = School::create(['name' => 'B']);
        // Seed unauthenticated so the tenant scope stays inert.
        Student::create(['school_id' => $a->id, 'name' => 'Ana']);
        Student::create(['school_id' => $b->id, 'name' => 'Bob']);

        $this->actingAs($this->admin($a));

        $students = Student::all();
        $this->assertCount(1, $students, 'admin must see only their own school');
        $this->assertSame('Ana', $students->first()->name);
    }

    public function test_creating_stamps_the_current_school_id(): void
    {
        $a = School::create(['name' => 'A']);
        $this->actingAs($this->admin($a));

        $student = Student::create(['name' => 'No School Given']);
        $this->assertSame($a->id, $student->school_id);
    }

    public function test_super_admin_sees_all_tenants(): void
    {
        $a = School::create(['name' => 'A']);
        $b = School::create(['name' => 'B']);
        Student::create(['school_id' => $a->id, 'name' => 'Ana']);
        Student::create(['school_id' => $b->id, 'name' => 'Bob']);

        $super = User::create([
            'name' => 'Super', 'email' => 'super@test.local',
            'password' => 'secret123', 'role' => Role::SuperAdmin, // school_id null
        ]);
        $this->actingAs($super);

        $this->assertCount(2, Student::all(), 'super admin bypasses tenant scope');
    }
}
