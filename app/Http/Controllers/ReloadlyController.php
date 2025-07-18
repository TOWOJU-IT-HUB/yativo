<?php

namespace App\Http\Controllers;

use App\Models\GiftCard;
use App\Models\Operators;
use App\Models\Reloadly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReloadlyController extends Controller
{
    private $clientId;
    private $clientSecret;
    private $giftCardBaseUrl;
    private $token;

    public function __construct()
    {
        $this->clientId        = env('RELOADLY_CLIENT_ID');
        $this->clientSecret    = env('RELOADLY_CLIENT_SECRET');
        $this->giftCardBaseUrl = env("RELOADLY_MODE", "sandbox") === "live" ? env("RELOADLY_LIVE_URL") : env("RELOADLY_TEST_URL");
        $this->token = $this->getAccessToken();
    }

    /**
     * Get Access Token from Reloadly API
     */
    public function getAccessToken($audience = null)
    {
        $response = Http::asForm()->post('https://auth.reloadly.com/oauth/token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'client_credentials',
            'audience'      => $audience ?? $this->giftCardBaseUrl,
        ]);

        if ($response->failed()) {
            Log::error('Failed to authenticate with Reloadly', $response->json());
            abort(500, 'Failed to authenticate with Reloadly.');
        }

        return $response->json()['access_token'] ?? null;
    }
    
    public function fetchGiftCardCategories()
    {
        // Select distinct categories and pluck the 'category_name' and 'category_id'
        $uniqueCategories = Operators::select('category_name', 'category_id')
            ->distinct()
            ->get()
            ->map(function ($category) {
                return [
                    'category_name' => $category->category_name,
                    'category_id' => $category->category_id,
                ];
            })
            ->toArray();
    
        // Assuming get_success_response is a custom helper function
        // If not, you can use Laravel's response method
        return get_success_response("Categories fetched successfully", $uniqueCategories);
    }

    /**
     * Fetch all gift cards from Reloadly (with optional filtering)
     */
    public function fetchGiftCards(Request $request)
    {
        $filters = $request->except(['page']); // Exclude pagination parameter
        $operators = Operators::getFilteredOperators($filters);

        return get_success_response("Operators retrieved successfully", $operators->toArray());
    }

    /**
     * Purchase a gift card
     */
   public function purchaseGiftCard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'productId'        => 'required|integer|exists:operators,product_id',
            'unitPrice'        => 'required|numeric|min:0.01',
            'quantity'         => 'required|integer|min:1',
            'senderName'       => 'required|string',
            'recipientEmail'   => 'required|email',
        ]);
    
        if ($validator->fails()) {
            return get_error_response($validator->errors(), 422,"Validation Error");
        }
    
        $validated = $validator->validated();
    
        // Cast unitPrice to float for strict comparison
        $validated['unitPrice'] = (float)$validated['unitPrice'];
    
        // Fetch the product details
        $product = Operators::whereProductId($validated['productId'])->first();
    
        // Validate unit price based on denomination type
        switch ($product->denomination_type) {
            case 'RANGE':
                if ($validated['unitPrice'] < $product->min_recipient_denomination || $validated['unitPrice'] > $product->max_recipient_denomination) {
                    return get_error_response(["error" => "Invalid unit price", ["unitPrice" => "Price must be between {$product->min_recipient_denomination} and {$product->max_recipient_denomination}"]], 422);
                }
                break;
    
            case 'FIXED':
                $fixedPrices = json_decode($product->fixed_recipient_denominations, true); // Ensure it's array of numbers
                if (!in_array($validated['unitPrice'], $fixedPrices)) {
                    return get_error_response(["error" => "Invalid unit price", [
                        "unitPrice" => "Price must be one of " . implode(', ', $fixedPrices) . " but you provided " . $validated['unitPrice']
                    ]], 422);
                }
                break;
    
            case 'MAPPED':
                $mappedPrices = json_decode($product->fixed_recipient_to_sender_map, true); // Ensure JSON decoded
                if (!isset($mappedPrices[$validated['unitPrice']])) {
                    return get_error_response(["error" => "Invalid unit price", ["unitPrice" => "Price must have a valid sender mapping"]], 422);
                }
                break;
    
            default:
                return get_error_response(["error" => "Invalid denomination type", ["denomination_type" => "Unsupported denomination type"]], 422);
        }
    
        $user = auth()->user();
    
        // Check if the user has a wallet
        $wallet = $user->wallets()->first();
        if (!$wallet) {
            $wallet = $user->createWallet();
        }
    
        $amount = $validated['unitPrice'] * $request->quantity;
    
        // Check if user has enough balance
        if ($wallet->balance < $amount) {
            return get_error_response(['error' => 'Insufficient wallet balance'], 402, 'Insufficient wallet balance');
        }
    
        // Deduct amount from wallet
        try {
            $wallet->withdraw($amount, ['description' => 'Gift card purchase', 'type' => 'giftcards']);
        } catch (\Exception $e) {
            return get_error_response(['error' => 'Wallet deduction failed: ' . $e->getMessage()], 500, $e->getMessage());
        }
    
        $validated['recipientPhone'] = $user->phone ?? null;
        $validated['customIdentifier'] = $validated['customIdentifier'] ?? uniqid('giftcard_');
    
        // Proceed with API request
        $curl = Http::withToken($this->token)
            ->post("{$this->giftCardBaseUrl}/orders", $validated);
    
        $response = $curl->json();
    
        if ($curl->successful() && isset($response['balanceInfo'])) {
            unset($response['balanceInfo']);
            $httpCode = get_status_string($curl->status());
    
            $giftcard = GiftCard::create([
                'user_id' => $user->id,
                'transaction_id' => uniqid(),
                'status' => $httpCode,
                'currency_code' => $wallet->slug,
                'amount' => $amount,
                'fee' => 0,
                'total_fee' => 0,
                'recipient_email' => $request->recipientEmail,
                'custom_identifier' => $validated['customIdentifier'],
                'pre_ordered' => true,
                'purchase_data' => json_encode($response),
                'redeem_instructions' => '',
                'transaction_created_at' => now(),
            ]);
    
            // addTransactionRecord(
            //     $giftcard->user_id,
            //     'gift_card',
            //     $amount,
            //     "usd",
            //     $request->phone ?? null,
            //     $giftcard,
            //     $httpCode
            // );
    
            return get_success_response("Giftcard purchased successfully", $response);
        }
    
        return get_error_response($curl->json(), 400, $curl->json('message') ?? 'Giftcard Purchase Failed');
    }
    
    public function redeem($transactionId)
    {
        try {
            // Retrieve instructions on how to redeem the gift card
            $redeem = Http::withToken($this->token)
                          ->get("{$this->giftCardBaseUrl}/redeem-instructions/{$transactionId}");
    
            if ($redeem->successful()) {
                return get_success_response("Giftcard instruction retrieved successfully", $redeem->json());
            }
    
            return get_error_response(["error" => $redeem->json()], error, "Failed to retrieve redeem instructions");
    
        } catch (\Throwable $e) {
            // Optionally log the error
            logger()->error("Giftcard redeem failed", [
                'transactionId' => $transactionId,
                'error' => $e->getMessage()
            ]);
    
            return get_error_response(['error' => "An error occurred while retrieving redeem instructions."]);
        }
    }


    /**
     * Fetch all gift cards from Reloadly and store in the database
     * For admin use only
     */
    public function fetchAndStoreGiftCards()
    {
        // Truncate the table before starting
        \DB::table('operators')->truncate();
    
        $page = 0;
        $pageSize = 200; // Adjust the page size as needed
        $allFetched = false;
    
        while (!$allFetched) {
            $response = Http::withToken($this->token)
                ->get("{$this->giftCardBaseUrl}/products", [
                    'page' => $page,
                    'size' => $pageSize,
                    'productCategoryId' => 1
                ]);
    
            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch gift cards'], 500);
            }
    
            $data = $response->json();
    
            // Process each gift card in the current page
            foreach ($data["content"] as $card) {
                Operators::updateOrCreate(
                    ['product_id' => $card['productId']],
                    [   
                        'product_name' => $card['productName'],
                        'global' => $card['global'],
                        'status' => $card['status'],
                        'supports_pre_order' => $card['supportsPreOrder'],
                        'sender_fee' => $card['senderFee'],
                        'sender_fee_percentage' => $card['senderFeePercentage'],
                        'discount_percentage' => $card['discountPercentage'],
                        'denomination_type' => $card['denominationType'],
                        'recipient_currency_code' => $card['recipientCurrencyCode'],
                        'min_recipient_denomination' => $card['minRecipientDenomination'],
                        'max_recipient_denomination' => $card['maxRecipientDenomination'],
                        'sender_currency_code' => $card['senderCurrencyCode'],
                        'min_sender_denomination' => $card['minSenderDenomination'],
                        'max_sender_denomination' => $card['maxSenderDenomination'],
                        'fixed_recipient_denominations' => json_encode($card['fixedRecipientDenominations']),
                        'fixed_sender_denominations' => json_encode($card['fixedSenderDenominations']),
                        'fixed_recipient_to_sender_map' => json_encode($card['fixedRecipientToSenderDenominationsMap']),
                        'logo_urls' => json_encode($card['logoUrls']),
                        'brand_id' => $card['brand']['brandId'],
                        'brand_name' => $card['brand']['brandName'],
                        'category_id' => $card['category']['id'],
                        'category_name' => $card['category']['name'],
                        'country_iso' => $card['country']['isoName'],
                        'country_name' => $card['country']['name'],
                        'country_flag_url' => $card['country']['flagUrl'],
                        'redeem_instruction_concise' => $card['redeemInstruction']['concise'],
                        'redeem_instruction_verbose' => $card['redeemInstruction']['verbose'],
                        'user_id_required' => $card['additionalRequirements']['userIdRequired'],
                    ]   
                );
            }
    
            // Check if this is the last page
            if ($page >= $data['totalPages'] - 1) {
                $allFetched = true;
            }
    
            $page++;
        }
    
        return response()->json(['message' => 'Gift cards successfully stored'], 200);
    }

    /**
     * Reloadly Webhook Handler
     */
    public function webhook(Request $request)
    {
        $allowedIPs = ['3.227.182.193'];
        $clientIP   = $request->ip();

        if (! in_array($clientIP, $allowedIPs)) {
            return response('Forbidden: Unauthorized IP address.', 403);
        }

        $signingSecret    = env('RELOADLY_WEBHOOK_SECRET', '');
        $payload          = $request->getContent();
        $requestSignature = $request->header('X-Reloadly-Signature');
        $requestTimestamp = $request->header('X-Reloadly-Request-Timestamp');

        if (! $requestSignature || ! $requestTimestamp) {
            return response('Missing required headers.', 400);
        }

        $computedSignature = hash_hmac('sha256', "$payload:$requestTimestamp", $signingSecret);
        if (! hash_equals($computedSignature, $requestSignature)) {
            return response('Invalid signature.', 400);
        }

        $event = json_decode($payload, true);
        if (! $event) {
            return response('Invalid JSON.', 400);
        }

        match ($event['type'] ?? '') {
            'giftcard_transaction.status' => Log::info('Processing gift card transaction!', $event),
            default => Log::warning('Unhandled event type: ' . ($event['type'] ?? 'unknown')),
        };

        return response('Webhook processed successfully.', 200);
    }


    function getReloadlyFxRate(Request $request)
    {
        $reloadly = new ReloadlyController();
        $audience = env("RELOADLY_MODE", "sandbox") === "live" ? "https://topups.reloadly.com" : "https://topups-sandbox.reloadly.com";
        $accessToken = $reloadly->getAccessToken($audience);

        $response = Http::withToken($accessToken)
            ->accept('application/com.reloadly.topups-v1+json')
            ->post("{$audience}/operators/fx-rate", [
                'operatorId' => $request->operatorId,
                'amount' => $request->amount,
            ]);

        if ($response->successful()) {
            return get_success_response('FX Rate retreived successfully', $response->json());
            // return $response->json();
        }

        return get_error_response($response->json(), 400, $response->json('message') ?? 'Request failed.');
    }
}

