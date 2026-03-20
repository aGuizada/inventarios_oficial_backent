<?php

namespace App\Providers;

use App\Models\Caja;
use App\Models\CompraBase;
use App\Models\Traspaso;
use App\Models\Venta;
use App\Observers\TraspasoObserver;
use App\Observers\VentaObserver;
use App\Policies\CajaPolicy;
use App\Policies\CompraPolicy;
use App\Policies\TraspasoPolicy;
use App\Policies\VentaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(Venta::class, VentaPolicy::class);
        Gate::policy(CompraBase::class, CompraPolicy::class);
        Gate::policy(Caja::class, CajaPolicy::class);
        Gate::policy(Traspaso::class, TraspasoPolicy::class);

        // Register observers
        Venta::observe(VentaObserver::class);
        Traspaso::observe(TraspasoObserver::class);
    }
}
