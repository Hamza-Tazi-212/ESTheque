<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $requiredRole = UserRole::tryFrom($role);

        if ($requiredRole === null || $request->user()?->role !== $requiredRole) {
            return response()->view('auth.failure', [
                'title' => 'Accès refusé',
                'message' => 'Votre compte ne dispose pas des droits nécessaires pour accéder à cette interface.',
            ], 403);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
