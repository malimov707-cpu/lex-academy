<?php

namespace App\Http\Middleware;

use App\Models\EducationAuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EducationAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        $response = $next($request);
        $actor ??= $request->user();
        if ($actor && ! $request->isMethodSafe() && $response->getStatusCode() < 400
            && ! in_array('errors', $request->session()->get('_flash.new', []), true)) {
            $subject = collect($request->route()?->parameters() ?? [])->first(fn ($value) => $value instanceof Model);
            EducationAuditLog::query()->create([
                'user_id' => $actor->id, 'action' => $request->route()?->getName() ?? $request->path(),
                'subject_type' => $subject ? class_basename($subject) : null, 'subject_id' => $subject?->getKey(),
                'properties' => ['method' => $request->method()],
            ]);
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'microphone=(self), camera=(), geolocation=()');

        return $response;
    }
}
