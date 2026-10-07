<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberLastAuthenticatedPage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRemember($request, $response)) {
            $request->session()->put('last_authenticated_url', $request->fullUrl());
        }

        return $response;
    }

    private function shouldRemember(Request $request, Response $response): bool
    {
        if (!$request->user() || !$request->isMethod('GET')) {
            return false;
        }

        if ($request->ajax() || $request->expectsJson() || $response->getStatusCode() >= 400) {
            return false;
        }

        $contentType = (string) $response->headers->get('content-type');

        return $contentType === '' || str_contains($contentType, 'text/html');
    }
}
