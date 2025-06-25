<?php
namespace Modules\Customer\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserMeta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\app\Models\Customer;
use Modules\Customer\app\Models\CustomerVirtualCards;
use Modules\Webhook\app\Models\Webhook;
use Spatie\WebhookServer\WebhookCall;
use Towoju5\Bitnob\Bitnob;

class CustomerVirtualCardsController extends Controller
{
    public $card;

    public function __construct()
    {
        $bitnob     = new Bitnob();
        $this->card = $bitnob->cards();

        // $this->middleware('vc_charge')->only(['store', 'topUpCard']);
    }

    public function index(Request $request)
    {
        try {
            $query = CustomerVirtualCards::where('business_id', get_business_id(auth()->id()));

            // Filter by customer_id if provided
            if ($request->has('customer_id') && ! empty($request->customer_id)) {
                $query->where('customer_id', $request->customer_id);
            }

            // Filter by created_between if both dates are provided
            if ($request->has('created_between') && is_array($request->created_between)) {
                [$start, $end] = $request->created_between;
                if ($start && $end) {
                    $query->whereBetween('created_at', [Carbon::parse($start)->startOfDay(), Carbon::parse($end)->endOfDay()]);
                }
            }

            $cards = $query->paginate(per_page())->withQueryString();

            return paginate_yativo($cards);
        } catch (\Exception $e) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function verifyUser()
    {
        try {
            $verify = $this->card->verifyUser();
            return get_success_response(['verify' => $verify]);
        } catch (\Exception $e) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function regUser(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "customer_id" => "required|exists:customers,customer_id",
                "user_photo"  => "required",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $cust = Customer::whereCustomerId($request->customer_id)->first();

            if (! $cust) {
                return get_error_response(['error' => "Customer not found!"]);
            }

            if ($cust->can_create_vc === true && $cust->vc_customer_id !== null) {
                return get_error_response([
                    "error" => "Customer already enrolled and activated",
                ], 421);
            }

            // Define required fields
            $requiredFields = [
                'address' => ['country', 'city', 'state', 'zipcode', 'street', 'number'],
                'top'     => ['customer_idFront', 'customer_idNumber'],
            ];

            $missingFields = [];

            // Build address array from customer or request
            $address = $cust->customer_address ?? $request->customer_address ?? [];

            foreach ($requiredFields['address'] as $field) {
                $value = $address[$field] ?? null;
                if (empty($value)) {
                    if (empty($request->input("customer_address.$field"))) {
                        $missingFields[] = "customer_address.$field";
                    } else {
                        $address[$field] = $request->input("customer_address.$field");
                    }
                }
            }

            foreach ($requiredFields['top'] as $field) {
                if (empty($cust->$field)) {
                    if (empty($request->$field)) {
                        $missingFields[] = $field;
                    } else {
                        $cust->$field = $request->$field;
                    }
                }
            }

            // Return error if any field is still missing
            if (! empty($missingFields)) {
                return get_error_response($missingFields, 422, "Missing required customer data.");
            }

            // Save updated data to DB
            $cust->customer_address = $cust->customer_address ?: $address;
            $cust->save();

            // Prepare payload
            $customerName                   = explode(" ", $cust->customer_name);
            $validatedData                  = $validate->validated();
            $validatedData['date_of_birth'] = $request->dateOfBirth ?? $request->date_of_birth;
            $validatedData['dateOfBirth']   = $request->dateOfBirth ?? $request->date_of_birth;
            $validatedData['firstName']     = $customerName[0];
            $validatedData["customerEmail"] = $cust->customer_email;
            $validatedData["phoneNumber"]   = $cust->customer_phone;
            $validatedData["idImage"]       = $cust->customer_idFront;
            $validatedData["country"]       = $cust->customer_country;
            $validatedData["city"]          = $address['city'];
            $validatedData["state"]         = $address['state'];
            $validatedData["zipCode"]       = $address['zipcode'];
            $validatedData["line1"]         = $address['street'];
            $validatedData["houseNumber"]   = $address['number'];
            $validatedData["idType"]        = "NATIONAL_ID";
            $validatedData["idNumber"]      = $cust->customer_idNumber;
            if (isset($customerName[1]) && strlen($customerName[1]) >= 3) {
                $validatedData['lastName'] = $customerName[1];
            } elseif (isset($customerName[2]) && strlen($customerName[2]) >= 3) {
                $validatedData['lastName'] = $customerName[2];
            } else {
                $validatedData['lastName'] = $customerName[0];
            }
            $validatedData['userPhoto'] = $request->user_photo;

            // Call card API
            $req = $this->card->regUser($validatedData);
            if (! is_array($req)) {
                $req = (array) $req;
            }

            if (isset($req['errorCode']) && $req['errorCode'] >= 400) {
                return get_error_response(['error' => "Error, Please contact support."]);
            } elseif (isset($req['status']) && $req['status'] == true) {
                $cust->can_create_vc  = true;
                $cust->vc_customer_id = $req['data']['id'];
                $cust->save();
                return get_success_response(['success' => "Customer activated successfully."]);
            } else {
                return get_error_response($req);
            }

        } catch (\Throwable $th) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTraceAsString()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function store(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'customer_id' => 'required|exists:customers,customer_id',
                'amount'      => 'required|numeric|min:5',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $where = [
                'customer_id' => $request->customer_id,
                'user_id'     => auth()->id(),
            ];

            $cust = Customer::where($where)->first();

            if (! $cust) {
                return get_error_response(['error' => "Customer with the provided ID not found!"]);
            }

            // debit user for card creation
            // Step 1: Get custom pricing (returns float_fee and fixed_fee)
            $pricing = get_custom_pricing('card_creation', 3, 'virtual_card');

            // Step 2: Get amount from request
            $amount = floatval($request->amount);

            // Step 3: Calculate the total fee using float % and fixed fee
            $floatCharge = $amount * ($pricing['float_fee'] / 100);
            $fee         = $floatCharge + $pricing['fixed_fee'];

            // Step 4: Debit user's wallet (convert to cents)
            if (! debit_user_wallet(intval($fee * 100), "USD", "Virtual Card Creation")) {
                return get_error_response(['error' => 'Error while charging for card creation']);
            }

            // Ensure the customer_email field is available and correctly fetched
            if (! $cust->customer_email) {
                return get_error_response(['error' => "Customer email not found!"]);
            }

            $data = [
                'customerEmail' => $cust->customer_email,
                'cardBrand'     => 'visa',
                'cardType'      => 'virtual',
                'reference'     => generate_uuid(),
                'amount'        => $request->amount, // amount should be passed in cents
            ];

            // Check how many cards the customer has;
            // max of 3 is allowed per customer
            $cardCount = CustomerVirtualCards::where([
                'customer_id' => $request->customer_id,
                'business_id' => get_business_id(auth()->id()),
            ])->count();

            if ($cardCount >= 2) {
                return get_error_response(['error' => "Customer can create at most 3 cards"]);
            }

            $bitnob = new Bitnob();
            $cards  = $bitnob->cards();
            $create = $cards->create($data);
            // var_dump(['response' => $create, 'payload' => $data]);
            if (! is_array($create)) {
                $create = (array) $create;
            }

            if (isset($create['status']) && $create['status'] === true) {
                // Save card details into DB, call get card to retrieve card details
                $cardId = $create['data']['id'];

                // store the card in the db against the card ID.
                $businessId = get_business_id(auth()->id());
                // $save_card_id = CustomerVirtualCards::updateOrCreate(
                //     [
                //         "card_id" => $cardId,
                //         'business_id' => $businessId,
                //         'customer_id' => $request->customer_id,
                //     ], [
                //         "card_id" => $cardId,
                //         'business_id' => $businessId,
                //         'customer_id' => $request->customer_id,
                //         'customer_card_id' => $cardId,
                //     ]);

                $save_card_id = CustomerVirtualCards::updateOrCreate(
                    [
                        'card_id'     => $cardId,
                        'business_id' => $businessId,
                        'customer_id' => $cust->id,
                    ],
                    [
                        'customer_card_id' => $cardId,
                    ]
                );


                $user_meta_payload = [
                    "user_id"                   => auth()->id(),
                    "customer_id"               => $cust->id,
                    "card_id"                   => $cardId,
                    "request_payload"           => $data,
                    "provider_response_payload" => $create,
                ];

                UserMeta::create([
                    'user_id' => auth()->id(),
                    'key'     => $cardId,
                    'value'   => json_encode($user_meta_payload),
                ]);

                $getCard = self::show($cardId, true);
                Log::error("this is the card details: ", $getCard);

                if ($getCard && $save = $this->saveVirtualCard($getCard, $cardId, $request)) {
                    return get_success_response($save);
                }

                // Data to be returned
                $arr = [
                    "card_id"        => $cardId,
                    "customer_email" => $cust->customer_email,
                    "customer_id"    => $cust->customer_id,
                    "card_brand"     => "visa",
                    "card_type"      => "virtual",
                ];

                return get_success_response($arr);
            }

            if (isset($create['errorCode']) || isset($create['error'])) {
                // credit_user_wallet();
                return get_error_response($create["message"] ?? $create);
            }

            if (isset($create['statusCode']) || isset($create['error'])) {
                // credit_user_wallet();
                return get_error_response($create["message"] ?? $create);
            }
        } catch (\Throwable $th) {
            // Log::error("Bitnob error:", $th->getTrace());
            // if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            // }
            // return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function saveVirtualCard($card, $cardId, $request)
    {
        $businessId = get_business_id(auth()->id());

        $virtualCard = CustomerVirtualCards::updateOrCreate(
            [
                'business_id' => $businessId,
                'customer_id' => $request->customer_id,
                'customer_card_id' => $cardId,
            ],
            [
                'card_number' => $card['cardNumber'],
                'expiry_date' => $card['valid'],
                'cvv'         => $card['cvv2'],
                'card_id'     => $cardId,
                'raw_data'    => $card,
            ]
        );

        return $virtualCard ? $virtualCard->toArray() : false;
    }

    /**
     * Add a cronjob to pull details of virtual cards that failed to pull on creation
     * Modules\Customer\app\Http\Controllers\CustomerVirtualAccountController::virtualCardCronJob
     * @param string cardId
     */
    public function virtualCardCronJob()
    {
        $cards = CustomerVirtualCards::whereNull('card_number')->get();
        foreach ($cards as $index => $card) {
            $cardInfo = $this->card->getCard($card->card_id);

            if (empty($cardInfo)) {
                return false;
            }

            if (! is_array($cardInfo)) {
                $cardInfo = (array) $cardInfo;
            }

            $arr     = ["reference", "createdStatus", "customerId", "customerEmail", "status", "cardUserId", "createdAt", "updatedAt"];
            $arrData = [];

            if (isset($cardInfo['data'])) {
                $arrData = $cardInfo['data'];
                foreach ($arr as $key) {
                    unset($arrData[$key]);
                }
                // handle error cases inside the data block
                if (isset($arrData['error']) || (isset($arrData['statusCode']) && (int) $arrData['statusCode'] === 500)) {
                    return false;
                }
            }

            // update card details in DB
            $card->update([
                'card_number' => $card['cardNumber'],
                'expiry_date' => $card['valid'],
                'cvv'         => $card['cvv2'],
                'card_id'     => $card->card_id,
                'raw_data'    => $card,
            ]);
        }
    }

    public function show($cardId, $arrOnly = false)
    {
        try {
            // check if card belongs to the customer
            $businessId = get_business_id(auth()->id());
            $card = CustomerVirtualCards::where('business_id', $businessId)->where('card_id', $cardId)->first();
            if(!$card) {
                return get_error_response(['error' => "Card not found"]);
            }
            $cardData = $this->card->getCard($cardId);

            if (empty($cardData)) {
                return $arrOnly ? null : get_error_response(['error' => "Card not found!"], 404);
            }

            if (! is_array($cardData)) {
                $cardData = (array) $cardData;
            }

            $arr     = ["reference", "createdStatus", "customerId", "customerEmail", "status", "cardUserId", "createdAt", "updatedAt"];
            $arrData = [];

            if (isset($cardData['data'])) {
                $arrData = $cardData['data'];

                foreach ($arr as $key) {
                    unset($arrData[$key]);
                }

                // handle error cases inside the data block
                if (isset($arrData['error']) || (isset($arrData['statusCode']) && (int) $arrData['statusCode'] === 500)) {
                    return $arrOnly ? null : get_error_response(['error' => $arrData['message']], $arrData['statusCode'] ?? 400);
                }
            }

            // update card details in DB
            if($card->card_number == null) {
                $card->update([
                    'card_number' => $arrData['cardNumber'],
                    'expiry_date' => $arrData['valid'],
                    'cvv'         => $arrData['cvv2'],
                    'raw_data'    => $arrData,
                ]);
            }

            return $arrOnly ? $arrData : get_success_response($arrData);
        } catch (\Exception $e) {
            return $arrOnly
            ? null
            : get_error_response([
                'error' => env('APP_ENV') === 'local'
                ? $e->getMessage() : 'Something went wrong, please try again later',
            ]);
        }
    }

    public function update(Request $request, $cardId)
    {
        try {
            $validate = Validator::make($request->all(), [
                "action" => "required|in:freeze,unfreeze",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $card = $this->card->action($request->action, $cardId);
            return get_success_response(['action' => $card['message']]);
        } catch (\Exception $e) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function getTransactions($cardId)
    {
        try {
            $bitnob = $this->card->getTransaction($cardId);

            if (isset($bitnob['status']) && $bitnob['status'] == true) {
                return get_success_response($bitnob['data']['cardTransactions']);
            }

            return get_error_response(['error' => "Error encountered, please check your request"]);
        } catch (\Throwable $th) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function topUpCard(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "customer_id" => "required",
                "cardId"      => "required",
                "amount"      => "required|numeric|min:1",
            ]);

            if ($validator->fails()) {
                return get_error_response($validator->errors()->toArray());
            }

            $customer = Customer::where('customer_id', $request->customer_id)
                ->where('user_id', auth()->id())
                ->first();

            if (! $customer) {
                return get_error_response(['error' => 'Invalid customer ID provided']);
            }

            $data = [
                'cardId'    => $request->cardId,
                'reference' => generate_uuid(),
                'amount'    => floatval($request->amount * 100),
            ];

            // Calculate top-up fee
            // Calculate top-up fee using custom pricing
            $getFee = get_custom_pricing('card_creation', 3, 'virtual_card');

            // Ensure $request->amount is a float
            $amount = floatval($request->amount);

                                                                   // Apply float and fixed fees
            $floatCharge = $amount * ($getFee['float_fee'] / 100); // e.g., 1.5% => 0.015
            $topUpFee    = $floatCharge + $getFee['fixed_fee'];

            // Enforce a minimum fee of $1
            $topUpFee = max(1, $topUpFee);
            $topUpFee = $topUpFee + amount;
            // Debit the user's wallet
            if (! debit_user_wallet(floatval($topUpFee * 100), "USD", "Virtual Card Creation")) {
                return get_error_response(['error' => 'Error while charging for card creation']);
            }

            // Proceed with card creation logic...
            // e.g., call card provider API or create local card record

            // return get_success_response(['message' => 'Card created successfully', 'charged_fee' => $topUpFee]);

            // make request to bitnob to topup card
            $bitnob = $this->card->topup($data);

            if (isset($bitnob['errorCode'])) {
                return get_error_response(['error' => 'Error please confirm card is active, or contact support if error persist']);
            }

            if (isset($bitnob['status']) && $bitnob['status'] == true) {
                return get_success_response(["message" => "card topup in progress"]);
            }

            return get_error_response($bitnob);
        } catch (\Throwable $th) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function transactions($cardId)
    {
        try {
            $card = $this->card->getTransaction($cardId);
            return get_success_response(['transactions' => $card]);
        } catch (\Exception $e) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function terminateCard(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "cardId" => "required",
            ]);

            if ($validator->fails()) {
                return get_error_response($validator->errors()->toArray());
            }

            $card = CustomerVirtualCards::where('customer_card_id', $request->cardId)
                ->where('business_id', get_business_id(auth()->id()))
                ->first();

            if (! $card) {
                return get_error_response(['error' => 'Card not found!']);
            }

            // Charge termination fee
            // 1️⃣  Look up custom pricing (fallback fixed‑fee = $1 if none found)
            $pricing = get_custom_pricing('card_termination', 1, 'virtual_card');

                              // 2️⃣  Card‑termination is typically a flat charge, but we’ll still honour any %
            $floatCharge = 0; // most cases: 0 %
            if ($pricing['float_fee'] > 0) {
                $baseAmount  = 0; // change to your own logic if needed
                $floatCharge = $baseAmount * ($pricing['float_fee'] / 100);
            }

            $fee = $floatCharge + $pricing['fixed_fee'];

            // 4️⃣  Debit the user’s wallet (convert dollars ➔ cents)
            if (! debit_user_wallet(intval($fee * 100), 'USD', 'Card Termination Fee')) {
                return get_error_response(['error' => 'Error while charging for card termination']);
            }

            // Call Bitnob API to terminate the card
            $bitnob   = new Bitnob();
            $response = $bitnob->cards()->terminate($request->cardId);

            if (isset($response['status']) && $response['status'] === true) {
                $remainingBalance = $response['data']['balance']; 

                $user = User::whereId($card->user_id)->first();
                $wallet = $user->getWallet('usd');
                $wallet->deposit($remainingBalance, ['desription' => "Card Termination Refund"]);
                // Delete the card from the database
                $card->delete();
                return get_success_response(['message' => 'Card terminated successfully.']);
            }

            return get_error_response($response);
        } catch (\Throwable $th) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function handleChargeback($event)
    {
        try {
            $cardId = $event['cardId'];
            $card = CustomerVirtualCards::where('customer_card_id', $cardId)
                ->where('business_id', get_business_id(auth()->id()))
                ->first();

            if (! $card) {
                return get_error_response(['error' => 'Card not found!']);
            }

            // Charge chargeback fee
            $pricing = get_custom_pricing('charge_back', 60, 'virtual_card');
            $floatCharge = 0; // most cases: 0 %
            if ($pricing['float_fee'] > 0) {
                $baseAmount  = 0; // change to your own logic if needed
                $floatCharge = $baseAmount * ($pricing['float_fee'] / 100);
            }

            $fee = $floatCharge + $pricing['fixed_fee'];

            // Debit the user’s wallet (convert dollars ➔ cents)
            if (! debit_user_wallet(intval($fee * 100), 'USD', 'Card Termination Fee')) {
                return get_error_response(['error' => 'Error while charging for card termination']);
            }
            return true;
        } catch (\Throwable $th) {
            $cardId = $event['data']['cardId'];
            Log::error("unable to process charge back on cardID: {$cardId}", ['error' => $th->getMessage()]);
            return false;
        }
    }

    public function handleCardDeclined($event)
    {
        try {
            $cardId = $event['data']['cardId'];
            $card = CustomerVirtualCards::where('customer_card_id', $cardId)
                ->where('business_id', get_business_id(auth()->id()))
                ->first();

            if (! $card) {
                return get_error_response(['error' => 'Card not found!']);
            }

                                                              // Check if this is the third failed transaction
            $failedTransactions = $card->failed_transactions; // Assuming this is stored in the card model
            if (isset($event[$event]) && $event['event'] == "virtualcard.transaction.declined.frozen") {
                // Charge termination fee
                $pricing = get_custom_pricing('card_decline', 1, 'virtual_card');
                $floatCharge = 0; // most cases: 0 %
                if ($pricing['float_fee'] > 0) {
                    $baseAmount  = 0; // change to your own logic if needed
                    $floatCharge = $baseAmount * ($pricing['float_fee'] / 100);
                }

                $fee = $floatCharge + $pricing['fixed_fee'];

                // Debit the user’s wallet (convert dollars ➔ cents)
                if (! debit_user_wallet(intval($fee * 100), 'USD', 'Card Termination Fee')) {
                    return get_error_response(['error' => 'Error while charging for card termination']);
                }
                return true;
            }

            // Increment failed transaction count
            $card->failed_transactions = $failedTransactions + 1;
            $card->save();

            return get_success_response(['message' => 'Failed transaction recorded.']);
        } catch (\Throwable $th) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function webhook(Request $request)
    {
        $event = $request->input('event');
        $data  = $request->input('data');

        $webhookSecret = env("BITNOB_WEBHOOK_SECRET");
        $data          = json_encode($_POST);
        $hash          = hash_hmac('sha512', $data, $webhookSecret);

        if ($hash != $_SERVER['x-bitnob-signature']) {
            return http_response_code(200);
        }

        Log::info("Webhook received: {$event}", ['payload' => $data]);

        match ($event) {
            'virtualcard.created.success' => $this->handleCardCreatedSuccess($data),
            'virtualcard.created.failed' => $this->handleCardCreatedFailed($data),

            'virtualcard.topup.success' => $this->handleTopupSuccess($data),
            'virtualcard.topup.failed' => $this->handleTopupFailed($data),

            'virtualcard.withdrawal.success' => $this->handleWithdrawalSuccess($data),
            'virtualcard.withdrawal.failed' => $this->handleWithdrawalFailed($data),

            'virtualcard.transaction.debit' => $this->handleTransactionDebit($data),
            'virtualcard.transaction.reversed' => $this->handleTransactionReversed($data),
            'virtualcard.transaction.declined' => $this->handleTransactionDeclined($data),
            'virtualcard.transaction.declined.frozen' => $this->handleDeclinedFrozen($data),
            'virtualcard.transaction.declined.terminated' => $this->handleDeclinedTerminated($data),
            'virtualcard.transaction.authorization.failed' => $this->handleAuthorizationFailed($data),
            'virtualcard.transaction.crossborder' => $this->handleCrossBorder($data),
            'virtualcard.transaction.terminated.refund' => $this->handleTerminatedRefund($data),

            'virtualcard.user.kyc.success' => $this->handleKycSuccess($data),
            'virtualcard.user.kyc.failed' => $this->handleKycFailed($data),

            default => Log::warning("Unhandled virtual card webhook event: {$event}", $data),
        };

        return response()->json(['status' => 'ok']);
    }

    protected function handleCardCreatedSuccess(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card created successfully', $data);
        // Additional logic for handling successful card creation
        $virtual_card = $this->show($cardId, $arrOnly = true);
        Log::error(['virtual_card_created_event' => $data]);
        if (! $virtual_card) {
            return false;
        }

        $cardData        = UserMeta::where('key', $cardId)->first();
        $cardDataPayload = json_decode($cardData->value, true);

        if ($virtual_card) {
            $user = User::whereId($cardData->user_id)->first();
            if ($user) {
                $virtualCard                   = new CustomerVirtualCards();
                $virtualCard->business_id      = get_business_id($cardData->user_id);
                $virtualCard->customer_id      = $cardDataPayload['customer_id'];
                $virtualCard->customer_card_id = $cardId;
                $virtualCard->card_number      = $virtual_card['cardNumber'];
                $virtualCard->expiry_date      = $virtual_card['valid'];
                $virtualCard->cvv              = $virtual_card['cvv2'];
                $virtualCard->card_id          = $cardId;
                $virtualCard->raw_data         = json_encode($virtual_card);

                if ($virtualCard->save()) {
                    return $virtualCard->toArray();
                    // send webhook event to customer
                    $this->dispatchWebhookEvent($data, $user->id);
                }

                return false;
            }
        }
    }

    protected function handleCardCreatedFailed(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::error('Virtual card creation failed', $data);
        // Additional logic for handling failed card creation
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleTopupSuccess(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card top-up successful', $data);
        // Additional logic for handling successful top-up
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleTopupFailed(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::error('Virtual card top-up failed', $data);
        // Additional logic for handling failed top-up
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleWithdrawalSuccess(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card withdrawal successful', $data);
        // Additional logic for handling successful withdrawal
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleWithdrawalFailed(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::error('Virtual card withdrawal failed', $data);
        // Additional logic for handling failed withdrawal
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleTransactionDebit(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card transaction debit', $data);
        // Additional logic for handling transaction debit
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleTransactionReversed(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card transaction reversed', $data);
        // Additional logic for handling transaction reversal
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleTransactionDeclined(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card transaction declined', $data);
        // Additional logic for handling transaction decline
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleDeclinedFrozen(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card transaction declined (frozen)', $data);
        // Additional logic for handling transaction decline (frozen)
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleDeclinedTerminated(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card transaction declined (terminated)', $data);
        // Additional logic for handling transaction decline (terminated)
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleAuthorizationFailed(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card authorization failed', $data);
        // Additional logic for handling authorization failure
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleCrossBorder(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card cross-border transaction', $data);
        // Additional logic for handling cross-border transactions
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleTerminatedRefund(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card terminated refund', $data);
        // Additional logic for handling terminated refund
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleKycSuccess(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::info('Virtual card KYC success', $data);
        // Additional logic for handling KYC success
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function handleKycFailed(array $data)
    {
        $cardId = $data['cardId'] ?? null;
        if (null == $cardId || empty($cardId)) {return false;}
        Log::error('Virtual card KYC failed', $data);
        // Additional logic for handling KYC failure
        $virtual_card = $this->show($cardId, $arrOnly = true);
        if (! $virtual_card) {
            return false;
        }

        if ($virtual_card) {
            $user = $virtual_card->user();
            if ($user) {
                // send webhook event to customer
                $this->dispatchWebhookEvent($data, $user->id);
            }
        }
    }

    protected function dispatchWebhookEvent($event, $userId)
    {
        $webhook_url = Webhook::whereUserId($userId)->first();

        if ($webhook_url) {
            WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload($event)->dispatchSync();
        }
    }
}

