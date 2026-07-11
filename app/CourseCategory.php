<?php

namespace App;

enum CourseCategory: string
{
    case Faculty = 'faculty';
    case Departmental = 'departmental';
    case Elective = 'elective';

    public function label(): string
    {
        return match ($this) {
            self::Faculty => 'Faculty Course',
            self::Departmental => 'Departmental Course',
            self::Elective => 'Elective Course',
        };
    }
}
