<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The public site is the Next.js frontend. Laravel's web routes serve only the
// Filament admin panel (/admin) and Sanctum's CSRF cookie endpoint.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
    'frontend' => config('onushondhan.frontend_url'),
]));
