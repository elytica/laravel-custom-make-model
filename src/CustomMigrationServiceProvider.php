<?php
namespace Elytica\LaravelCustomMakeModel;

use Illuminate\Support\ServiceProvider;
use Elytica\LaravelCustomMakeModel\Commands\CustomModelMigration;

class CustomMigrationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CustomModelMigration::class,
            ]);
        }
    }
}
