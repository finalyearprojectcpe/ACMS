<?php

namespace App;

enum Grade: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';
    case F = 'F';

    public function points(): int
    {
        return match ($this) {
            self::A => 5,
            self::B => 4,
            self::C => 3,
            self::D => 2,
            self::E => 1,
            self::F => 0,
        };
    }
}
