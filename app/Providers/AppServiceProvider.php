<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Venta;
use App\Models\Traspaso;
use App\Observers\VentaObserver;
use App\Observers\TraspasoObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers
        Venta::observe(VentaObserver::class);
        Traspaso::observe(TraspasoObserver::class);
    }
}
