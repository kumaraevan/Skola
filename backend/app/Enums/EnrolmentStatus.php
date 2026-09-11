<?php

namespace App\Enums;

enum EnrolmentStatus: string
{
    case Pending = 'pending_enrolment'; // account created, no face yet
    case Enrolled = 'enrolled';         // initial face scan done
    case Inactive = 'inactive';         // left / disabled
}
