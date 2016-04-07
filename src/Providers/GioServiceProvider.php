<?php

namespace Gio\IijDagClient\Providers;

use Gio\IijDagClient\DagAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class GioServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('dag', function($app, $config) {
            return new Filesystem(new DagAdapter($config));
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('iij-gio-dag', function($app)
        {
            return new DagAdapter(config('filesystems.disks.dag'));
        });
    }
}
