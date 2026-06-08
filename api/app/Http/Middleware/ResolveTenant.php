<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->currentTenant->set($request->user()?->tenant);

        return $next($request);
    }
}
