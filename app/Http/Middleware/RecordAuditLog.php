<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAuditLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user()
            || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || $response->getStatusCode() >= 400
            || $request->session()->has('errors')) {
            return $response;
        }

        $subject = collect($request->route()?->parameters() ?? [])
            ->first(fn ($parameter) => $parameter instanceof Model);

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'event' => $request->route()?->getName() ?? $request->method().' '.$request->path(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'method' => $request->method(),
        ]);

        return $response;
    }
}
