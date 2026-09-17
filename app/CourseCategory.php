<?php

namespace App;

enum CourseCategory: string
{
    case Normal = 'normal';
    case Elective = 'elective';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal Course',
            self::Elective => 'Elective Course',
        };
    }
}
