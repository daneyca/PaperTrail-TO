<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockBacsec001SvpTask
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->user_id === 'BACSEC-001', 403);

        return $next($request);
    }
}
