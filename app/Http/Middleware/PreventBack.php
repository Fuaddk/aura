<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PreventBack
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip cache headers for file downloads (PDF, images, etc.)
        if ($response instanceof BinaryFileResponse) {
            return $response;
        }

        return $response
            ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
    }
}
