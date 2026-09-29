<?php

namespace App;

enum AccountType: string
{
    case Adviser = 'adviser';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Adviser => 'Course Adviser',
            self::Student => 'Student',
        };
    }
}
