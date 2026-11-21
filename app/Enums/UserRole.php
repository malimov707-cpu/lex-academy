<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case AcademyAdmin = 'academy_admin';
    case Teacher = 'teacher';
    case Mentor = 'mentor';
    case Student = 'student';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::SuperAdmin->value => 'Super Admin',
            self::AcademyAdmin->value => 'Academy Admin',
            self::Teacher->value => 'Teacher',
            self::Mentor->value => 'Mentor',
            self::Student->value => 'Student',
        ];
    }
}
