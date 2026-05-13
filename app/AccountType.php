<?php

namespace App;

enum AccountType: string
{
    case Student = 'student';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Staff => 'Staff',
        };
    }
}
