<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403, 'Akun ini sudah dinonaktifkan.');
        abort_unless($user->hasOutletAccess(), 403, $user->outlet?->status === 'DEMO'
            ? 'Masa demo outlet telah berakhir. Hubungi admin untuk mengaktifkan akses setelah pembayaran.'
            : 'Akses outlet ini sedang nonaktif. Hubungi admin untuk informasi lebih lanjut.');

        return $next($request);
    }
}
