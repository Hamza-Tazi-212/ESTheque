<?php

namespace App\Providers;

use App\Auth\DirectoryUserProvider;
use App\Contracts\UserDirectory;
use App\Repositories\EloquentUserDirectory;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserDirectory::class, EloquentUserDirectory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('directory', fn (Application $app) => new DirectoryUserProvider(
            $app->make(UserDirectory::class)
        ));
    }
}
