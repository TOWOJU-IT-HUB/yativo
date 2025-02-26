<?php

namespace App\Providers;

use Auth;
use DB;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Log;
use App\Observers\DepositObserver;
use App\Observers\CustomerObserver;
use App\Models\Deposit;
use Modules\Customer\app\Models\Customer;


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
        Deposit::observe(DepositObserver::class);
        Customer::observe(CustomerObserver::class);
        Paginator::useTailwind();
        if (!config('app.debug')) {
            DB::listen(function ($query) {
                $ip = request()->ip();
                $payload = $query->bindings;
                $sql = $query->sql;
                $executionTime = $query->time;

                Log::channel('database')->info('Database Query Executed', [
                    'ip' => $ip,
                    'query' => $sql,
                    'bindings' => $payload,
                    'execution_time' => $executionTime . 'ms',
                    'user_id' => Auth::id() ?? 'guest',
                    'date_time' => now()
                ]);
            });
        }
    }
}
