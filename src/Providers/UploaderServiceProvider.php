<?php

namespace Audentio\LaravelUploader\Providers;

use Audentio\LaravelUploader\LaravelUploader;
use Illuminate\Support\ServiceProvider;

class UploaderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/audentioUploader.php', 'audentioUploader'
        );

        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'audentioUploader');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerMigrations();
            $this->registerPublishes();
        }
    }

    protected function registerMigrations(): void
    {
        if (LaravelUploader::runsMigrations()) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        }
    }

    protected function registerPublishes(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/audentioUploader.php' => config_path('audentioUploader.php'),
            __DIR__ . '/../../resources/lang' => resource_path('lang/vendor/audentioUploader'),
        ]);
    }
}