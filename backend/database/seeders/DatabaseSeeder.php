<?php

namespace Database\Seeders;

use App\Enums\EnrolmentStatus;
use App\Enums\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Platform operator (cross-tenant). school_id = null.
        User::create([
            'name' => 'Super Admin',
            'email' => 'super@skola.test',
            'password' => 'password',
            'role' => Role::SuperAdmin,
        ]);

        $school = School::create(['name' => 'SMA Nusantara', 'timezone' => 'Asia/Jakarta']);

        User::create([
            'school_id' => $school->id,
            'name' => 'Admin Sekolah',
            'email' => 'admin@skola.test',
            'password' => 'password',
            'role' => Role::SchoolAdmin,
        ]);

        $teacherUser = User::create([
            'school_id' => $school->id,
            'name' => 'Budi Guru',
            'email' => 'guru@skola.test',
            'password' => 'password',
            'role' => Role::Teacher,
        ]);
        $teacher = Teacher::create([
            'school_id' => $school->id,
            'user_id' => $teacherUser->id,
            'nip' => '198501012010011001',
            'enrolment_status' => EnrolmentStatus::Enrolled,
        ]);

        $classX = SchoolClass::create(['school_id' => $school->id, 'name' => 'X IPA 1', 'grade' => '10', 'homeroom_teacher_id' => $teacher->id]);
        $classXI = SchoolClass::create(['school_id' => $school->id, 'name' => 'XI IPA 1', 'grade' => '11']);

        $names = ['Ani Lestari', 'Bagus Pratama', 'Citra Dewi', 'Dedi Kurniawan', 'Eka Putri'];
        foreach ($names as $i => $name) {
            Student::create([
                'school_id' => $school->id,
                'name' => $name,
                'nis' => '2024' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'class_id' => $i % 2 === 0 ? $classX->id : $classXI->id,
                'enrolment_status' => $i < 2 ? EnrolmentStatus::Enrolled : EnrolmentStatus::Pending,
            ]);
        }
    }
}
