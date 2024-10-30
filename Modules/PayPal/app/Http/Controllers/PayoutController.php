<?php

namespace Modules\PayPal\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\payoutMethods;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Modules\SendMoney\app\Models\SendQuote;
use PayPal\Api\WebhookEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// use Sample\PayPalClient;

class PayoutController extends Controller
{

    protected $paypalApiUrl;

    public function __construct()
    {
        $this->paypalApiUrl = env('PAYPAL_ENVIRONMENT') === 'live'
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';
    }

    /**
     * @param mixed $quoteId
     * @param string $currency
     * @param array $obj - withdrawal object
     * 
     * @return array
     */
    public function init(mixed $quoteId, string $currency, array $obj)
    {
        // echo json_encode($obj); exit;
        $request = request();
        $quote = SendQuote::whereId($quoteId)->first();
        $user = get_user($quote->user_id);
        $requestBody = [
            'sender_batch_header' => [
                'sender_batch_id' => generate_uuid(),
                'email_subject' => "Payout from {$user->name} via " . getenv('APP_NAME'),
                'email_message' => 'You have received a payout! Thanks for using our service!',
            ],
            'items' => [
                [
                    'recipient_type' => 'EMAIL',
                    'receiver' => $request->email,
                    'note' => $request->note ?? "Payout request from {$user->name}",
                    'sender_item_id' => generate_uuid(),
                    'amount' => [
                        'currency' => $request->currency,
                        'value' => $request->amount,
                    ],
                ],
            ],
        ];


        // Now you can use $token as your bearer token in subsequent requests
        $enviroment = $this->paypalApiUrl;
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->generateToken(),
        ])->post($enviroment . '/v1/payments/payouts', $requestBody)->json();

        return ['result' => $response];

    }

    /**
     * Generate and cache paypal bearer 
     * token to optimized and speed up 
     * requests
     * 
     * @return string
     */
    private function generateToken()
    {
        // Check if access token exists in cache
        if (Cache::has('paypal_access_token')) {
            return Cache::get('paypal_access_token');
        }

        $clientId = getenv('PAYPAL_CLIENT_ID');
        $clientSecret = getenv('PAYPAL_SECRET_ID');
        $paypalApiUrl = $this->paypalApiUrl;

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post("$paypalApiUrl/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ])->withBasicAuth($clientId, $clientSecret);

        $token = $response->json('access_token');

        // Cache the access token with an expiration time (e.g., 1 hour)
        Cache::put('paypal_access_token', $token, (($response->json('expires_in') / 60) - 3));

        return $token;

    }

    public function generatePayinUrl($amount, $currency)
    {
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => floatval($amount) // Replace with your desired amount
                    ]
                ]
            ]
        ];

        // Send request to create order
        $endpoint = "/v1/checkout/orders";
        $orderResponse = Http::withToken($this->generateToken())
            ->post($this->paypalApiUrl . $endpoint, $orderData);

        // Extract order ID from response
        $orderId = $orderResponse->json('id');

        // Construct checkout URL
        $checkoutUrl = "https://www.paypal.com/checkoutnow?token=$orderId";

        // Redirect user to checkout URL
        return [
            "deposit_url" => $checkoutUrl
        ];
    }

    public function payout_webhook(Request $request)
    {
        // $webhookId = $request->header('Paypal-Transmission-Id');

        // $event = WebhookEvent::validateAndGetReceivedEvent($request->getContent());

        // // Handle the event based on its type
        // switch ($event->event_type) {
        //     case 'PAYMENT.PAYOUTS-ITEM.SUCCEEDED':
        //         $this->handlePayoutSucceeded($event);
        //         break;

        //     case 'PAYMENT.PAYOUTS-ITEM.BLOCKED':
        //         $this->handlePayoutBlocked($event);
        //         break;

        //     case 'PAYMENT.PAYOUTS-ITEM.CANCELED':
        //         $this->handlePayoutCanceled($event);
        //         break;

        //     case 'PAYMENT.PAYOUTS-ITEM.DENIED':
        //         $this->handlePayoutDenied($event);
        //         break;

        //     case 'PAYMENT.PAYOUTS-ITEM.FAILED':
        //         $this->handlePayoutFailed($event);
        //         break;

        //     case 'PAYMENT.PAYOUTS-ITEM.HELD':
        //         $this->handlePayoutHeld($event);
        //         break;

        //     case 'PAYMENT.PAYOUTS-ITEM.REFUNDED':
        //         $this->handlePayoutRefunded($event);
        //         break;

        //     case 'PAYMENT.PAYOUTS-ITEM.RETURNED':
        //         $this->handlePayoutReturned($event);
        //         break;

        //     case 'PAYMENT.PAYOUTS-ITEM.UNCLAIMED':
        //         $this->handlePayoutUnclaimed($event);
        //         break;
        // }

        // return response()->json(['status' => 'success']);
    }

    /**
     * Handle PayPal payout succeeded event.
     *
     * @param  object  $event
     * @return void
     */
    protected function handlePayoutSucceeded($event)
    {
        // Update the payout record as succeeded
        $payout = Withdraw::where('paypal_payout_id', $event->resource->payout_batch_id)->first();
        if ($payout) {
            $payout->status = 'succeeded';
            $payout->save();
        }
    }

    /**
     * Handle PayPal payout blocked event.
     *
     * @param  object  $event
     * @return void
     */
    protected function handlePayoutBlocked($event)
    {
        // Update the payout record as blocked
        $payout = Withdraw::where('paypal_payout_id', $event->resource->payout_batch_id)->first();
        if ($payout) {
            $payout->status = 'blocked';
            $payout->save();
        }
    }

    /**
     * Handle PayPal payout canceled event.
     *
     * @param  object  $event
     * @return void
     */
    protected function handlePayoutCanceled($event)
    {
        // Update the payout record as canceled
        $payout = Withdraw::where('paypal_payout_id', $event->resource->payout_batch_id)->first();
        if ($payout) {
            $payout->status = 'canceled';
            $payout->save();
        }
    }

    /**
     * Handle PayPal payout denied event.
     *
     * @param  object  $event
     * @return void
     */
    protected function handlePayoutDenied($event)
    {
        // Update the payout record as denied
        $payout = Withdraw::where('paypal_payout_id', $event->resource->payout_batch_id)->first();
        if ($payout) {
            $payout->status = 'denied';
            $payout->save();
        }
    }

    /**
     * Handle PayPal payout failed event.
     *
     * @param  object  $event
     * @return void
     */
    protected function handlePayoutFailed($event)
    {
        // Update the payout record as failed
        $payout = Withdraw::where('paypal_payout_id', $event->resource->payout_batch_id)->first();
        if ($payout) {
            $payout->status = 'failed';
            $payout->save();
        }
    }

    /**
     * Handle PayPal payout held event.
     *
     * @param  object  $event
     * @return void
     */
    protected function handlePayoutHeld($event)
    {
        // Update the payout record as held
        $payout = Withdraw::where('paypal_payout_id', $event->resource->payout_batch_id)->first();
        if ($payout) {
            $payout->status = 'held';
            $payout->save();
        }
    }

    /**
     * Handle PayPal payout refunded event.
     *
     * @param  object  $event
     * @return void
     */
    protected function handlePayoutRefunded($event)
    {
        // Update the payout record as refunded
        $payout = Withdraw::where('paypal_payout_id', $event->resource->payout_batch_id)->first();
        if ($payout) {
            $payout->status = 'refunded';
            $payout->save();
        }
    }

    /**
     * Handle PayPal payout returned event.
     *
     * @param  object  $event
     * @return void
     */
    protected function handlePayoutReturned($event)
    {
        // Update the payout record as returned
        $payout = Withdraw::where('paypal_payout_id', $event->resource->payout_batch_id)->first();
        if ($payout) {
            $payout->status = 'returned';
            $payout->save();
        }
    }

    /**
     * Handle PayPal payout unclaimed event.
     *
     * @param  object  $event
     * @return void
     */
    protected function handlePayoutUnclaimed($event)
    {
        // Update the payout record as unclaimed
        $payout = Withdraw::where('paypal_payout_id', $event->resource->payout_batch_id)->first();
        if ($payout) {
            $payout->status = 'unclaimed';
            $payout->save();
        }
    }

}
