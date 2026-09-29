<?php

namespace App\Http\Middleware;

use App\AccountType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->account_type === AccountType::Student && ! $user->studentProfile()->exists()) {
            return redirect()->route('student-joins.status');
        }

        return $next($request);
    }
}
