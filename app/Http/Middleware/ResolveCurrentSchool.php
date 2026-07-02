<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentSchool
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomainAbbreviation = $this->schoolAbbreviationFromSubdomain($request);
        $abbreviation = $subdomainAbbreviation ?? config('app.default_school_abbreviation');

        if (is_string($abbreviation) && $abbreviation !== '') {
            $school = School::query()
                ->where('abbreviation', Str::upper($abbreviation))
                ->first();

            abort_if($school === null && $subdomainAbbreviation !== null, 404);

            if ($school !== null) {
                $request->attributes->set('school', $school);
            }
        }

        return $next($request);
    }

    private function schoolAbbreviationFromSubdomain(Request $request): ?string
    {
        $schoolDomain = trim((string) config('app.school_domain', ''), '.');

        if ($schoolDomain === '') {
            return null;
        }

        $host = Str::lower($request->getHost());
        $schoolDomain = Str::lower($schoolDomain);
        $domainSuffix = '.'.$schoolDomain;

        if (! Str::endsWith($host, $domainSuffix)) {
            return null;
        }

        $subdomain = Str::beforeLast($host, $domainSuffix);

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        return Str::upper($subdomain);
    }
}
