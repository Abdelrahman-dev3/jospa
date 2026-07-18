<?php

namespace App\Support;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;

class AuthRedirect
{
    public static function path(?User $user = null): string
    {
        $user ??= auth()->user();

        if ($user && $user->hasAnyRole(['admin', 'manager', 'receptionist'])) {
            return RouteServiceProvider::HOME;
        }

        return Route::has('frontend.home') ? route('frontend.home') : '/';
    }

    public static function pathWithQuery(?User $user = null, array $query = []): string
    {
        $path = static::path($user);

        if ($query === []) {
            return $path;
        }

        return $path.(str_contains($path, '?') ? '&' : '?').http_build_query($query);
    }
}
