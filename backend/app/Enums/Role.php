<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';   // us — platform operator, cross-tenant
    case SchoolAdmin = 'school_admin'; // school administration
    case Teacher = 'teacher';          // marks attendance; also an attendee
    case Parent = 'parent';            // views child, pays SPP
    case Student = 'student';          // views own attendance

    public function isAdmin(): bool
    {
        return in_array($this, [self::SuperAdmin, self::SchoolAdmin], true);
    }
}
