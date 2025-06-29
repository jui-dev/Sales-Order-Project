<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind custom services into the service container here.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Perform post-registration booting of services.
    }
} 