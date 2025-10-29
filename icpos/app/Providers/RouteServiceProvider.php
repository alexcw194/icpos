<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/';

    public function boot(): void
    {
        // ⇣ Batasi parameter agar hanya angka
        Route::pattern('salesOrder', '[0-9]+');
        Route::pattern('attachment', '[0-9]+');

        // (opsional, kalau kamu juga pakai parameter ini sebagai angka)
        Route::pattern('quotation', '[0-9]+');
        Route::pattern('invoice', '[0-9]+');
        Route::pattern('delivery', '[0-9]+');

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
