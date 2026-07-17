<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreservePaymentCallbackSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $shouldPreserveSession = $this->isUrPayCallback($request)
            && ! $request->cookies->has($this->sessionCookieName());

        $response = $next($request);

        if (! $shouldPreserveSession) {
            return $response;
        }

        $cookies = $response->headers->getCookies();
        if ($cookies === []) {
            return $response;
        }

        // URPAY may POST back cross-site without the existing session cookie.
        // If Laravel writes a fresh guest session here, it overwrites the
        // logged-in browser session and the user appears logged out.
        $response->headers->remove('Set-Cookie');

        foreach ($cookies as $cookie) {
            if (in_array($cookie->getName(), [$this->sessionCookieName(), 'XSRF-TOKEN'], true)) {
                continue;
            }

            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    private function isUrPayCallback(Request $request): bool
    {
        return $request->is('urpay/success')
            || $request->is('urpay/failure')
            || $request->is('urpay/cancel');
    }

    private function sessionCookieName(): string
    {
        return (string) config('session.cookie');
    }
}
