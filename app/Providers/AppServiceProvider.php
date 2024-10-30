<?php

namespace App\Providers;

use App\Mail\Transport\AzureTransport;
use Artisan;
use GuzzleHttp\Client;
use Illuminate\Mail\MailManager;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

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
        // Artisan::call('route:clear');
        Paginator::useTailwind();
    }
}
