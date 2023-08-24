<?php
namespace Elytica\LaravelCustomMakeModel;

use Illuminate\Support\ServiceProvider;
use Elytica\LaravelCustomMakeModel\Commands\CustomModelMigration;

class CustomMigrationServiceProvider extends ServiceProvider
{
    protected $packageName = "laravel-custom-make-model";
    public function boot() : void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CustomModelMigration::class,
            ]);
        }
        $this->publishes([
          __DIR__."/../config/{$this->packageName}.php"
          => config_path("{$this->packageName}.php")],
        'config');
    }

    public function register() : void {
        $this->mergeConfigFrom(
          __DIR__."/../config/{$this->packageName}.php"
          ,"{$this->packageName}.php");
    }
}
