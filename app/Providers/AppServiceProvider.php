<?php

namespace App\Providers;

use App\Models\Ingreso_d;
use Illuminate\Support\ServiceProvider;
use App\Observers\IngresoObserver;
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
         // Registrar el Observer para Ingreso_d
        // Ingreso_d::observe(IngresoObserver::class);
        
        // También puedes registrar otros observers aquí si los necesitas
        // Ejemplo: User::observe(UserObserver::class);
        
    }
}
