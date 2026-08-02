<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiServiceKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = config('services.ai_service.key');
        $providedKey = $request->header('X-Service-Key');

        if (! is_string($expectedKey) || $expectedKey === '') {
            return response()->json([
                'message' => 'AI service authentication is not configured.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (
            ! is_string($providedKey) ||
            ! hash_equals($expectedKey, $providedKey)
        ) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}

// وظيفته
// يقرأ المفتاح من X-Service-Key.
// يقارنه بالمفتاح الموجود داخل إعدادات Laravel.
// يستخدم hash_equals للمقارنة الآمنة.
// يعيد 401 إذا كان المفتاح خطأ.