<?php

namespace MatrixLab\LaravelAdvancedSearch;

use Illuminate\Support\ServiceProvider;
use MatrixLab\LaravelAdvancedSearch\Console\Commands\ModelsAllColumnsCommand;

class AdvancedSearchServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModelsAllColumnsCommand::class,
            ]);
        }
    }

    /**
     * Register any application services.
     */
    public function register()
    {

    }
}
