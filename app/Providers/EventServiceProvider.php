<?php

namespace App\Providers;

use App\Events\CreateUSDWalletEvent;
use App\Events\DebitCreditEvent;
use App\Listeners\DebitCreditListener;
use App\Listeners\MonnifyNotificationListener;
use Bhekor\LaravelMonnify\Events\NewWebHookCallReceived;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\CoinPayments\app\Http\Controllers\CoinPaymentsController;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;
use App\Observers\DepositObserver;
use App\Models\Deposit;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
            /*
             * Registered event listeners
             */
        Registered::class => [
            SendEmailVerificationNotification::class,
            CreateUSDWalletEvent::class,
        ],
        DebitCreditEvent::class => [
            DebitCreditListener::class,
        ],
        WebhookCallSucceededEvent::class => [
            'App\Listeners\LogSuccessfulWebhook',
        ],
        WebhookCallFailedEvent::class => [
            'App\Listeners\LogFailedWebhook',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
