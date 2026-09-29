<?php

namespace App;

enum Honorific: string
{
    case Mr = 'Mr';
    case Mrs = 'Mrs';
    case Engr = 'Engr';
    case Dr = 'Dr';
    case Prof = 'Prof';

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $honorific): array => [
                'value' => $honorific->value,
                'label' => $honorific->value,
            ],
            self::cases(),
        );
    }
}
