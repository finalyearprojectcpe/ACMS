<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class AccessCodeGenerator
{
    public function generate(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (User::query()->where('access_code', $code)->whereNull('access_code_used_at')->exists());

        return $code;
    }
}
