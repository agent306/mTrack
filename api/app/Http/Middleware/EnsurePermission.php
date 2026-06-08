<?php

namespace App\Http\Middleware;

use App\Support\Authorization\PermissionMatrix;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $module, string $level = PermissionMatrix::VIEW): Response
    {
        abort_unless($request->user()?->hasPermission($module, $level), 403);

        return $next($request);
    }
}
