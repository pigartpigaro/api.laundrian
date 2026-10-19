<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(in_array($request->user()?->role, ['OWNER', 'SUPERADMIN'], true), 403, 'Aksi ini hanya dapat dilakukan owner.');

        return $next($request);
    }
}
