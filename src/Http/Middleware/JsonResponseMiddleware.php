<?php

namespace Refynd\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonResponseMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // If response is already a JSON response, return as is
        if ($response->headers->get('Content-Type') === 'application/json') {
            return $response;
        }

        // If request accepts JSON, convert response to JSON
        if ($request->headers->get('Accept') === 'application/json' ||
            $request->headers->get('Content-Type') === 'application/json') {

            $content = $response->getContent();

            // Try to decode if it's already JSON
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Not valid JSON, wrap in response structure
                $data = ['success' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300,
                    'data' => $content,
                    'status_code' => $response->getStatusCode(),];
            }

            $response->setContent(json_encode($data));
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}
