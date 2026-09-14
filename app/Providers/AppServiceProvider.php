<?php

namespace App\Providers;

use App\Http\Controllers\Company\BillOfLadingController;
use App\Http\Controllers\Company\BillOfLadingControllerCompat;
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

        $this->app->bind(
            BillOfLadingController::class,
            BillOfLadingControllerCompat::class
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
