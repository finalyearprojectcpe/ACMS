<?php

namespace App;

enum AccountType: string
{
    case SchoolAdmin = 'school_admin';
    case Student = 'student';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::SchoolAdmin => 'School Admin',
            self::Student => 'Student',
            self::Staff => 'Staff',
        };
    }
}
