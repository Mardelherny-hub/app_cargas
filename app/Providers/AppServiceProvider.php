<?php

namespace App\Providers;

use App\Http\Controllers\Company\ShipmentItemController;
use App\Http\Controllers\Company\ShipmentItemControllerCompat;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            ShipmentItemController::class,
            ShipmentItemControllerCompat::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
