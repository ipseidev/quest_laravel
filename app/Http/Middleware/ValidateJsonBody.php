<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ValidateJsonBody
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isJson() && $request->getContent() !== '') {
            try {
                json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new BadRequestHttpException('Malformed JSON body.', $e);
            }
        }

        return $next($request);
    }
}
