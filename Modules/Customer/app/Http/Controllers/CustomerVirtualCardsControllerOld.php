<?php

namespace Modules\Customer\app\Http\Controllers;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\app\Models\Customer;
use Modules\Customer\app\Models\CustomerVirtualCards;
use Towoju5\Bitnob\Bitnob;

class CustomerVirtualCardsControllerOld extends Controller
{
    public $card;
    public function __construct()
    {
        $bitnob = new Bitnob();
        $this->card = $bitnob->cards();

        $this->middleware('vc_charge')->only(['store', 'topUpCard']);
    }

    public function index(Request $request)
    {
        try {
            $query = CustomerVirtualCards::where('business_id', get_business_id(auth()->id()));

            // Filter by customer_id if provided
            if ($request->has('customer_id') && !empty($request->customer_id)) {
                $query->where('customer_id', $request->customer_id);
            }

            // Filter by created_between if both dates are provided
            if ($request->has('created_between') && is_array($request->created_between)) {
                [$start, $end] = $request->created_between;
                if ($start && $end) {
                    $query->whereBetween('created_at', [Carbon\Carbon::parse($start)->startOfDay(), Carbon\Carbon::parse($end)->endOfDay()]);
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
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Register a customer for virtual card creation.
     * 
     * @return 
     */
   public function regUser(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "customer_id" => "required|exists:customers,customer_id",
                "user_photo" => "required"
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $cust = Customer::whereCustomerId($request->customer_id)->first();

            if (!$cust) {
                return get_error_response(['error' => "Customer not found!"]);
            }

            if ($cust->can_create_vc === true && $cust->vc_customer_id !== null) {
                return get_error_response([
                    "error" => "Customer already enrolled and activated"
                ], 421);
            }

            // Define required fields
            $requiredFields = [
                'address' => ['country', 'city', 'state', 'zipcode', 'street', 'number'],
                'top' => ['customer_idFront', 'customer_idNumber']
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
            if (!empty($missingFields)) {
                return get_error_response($missingFields, 422, "Missing required customer data.");
            }

            // Save updated data to DB
            $cust->customer_address = $cust->customer_address ?: $address;
            $cust->save();

            // Prepare payload
            $customerName = explode(" ", $cust->customer_name);
            $validatedData = $validate->validated();
            $validatedData['date_of_birth'] = $request->dateOfBirth ?? $request->date_of_birth;
            $validatedData['dateOfBirth'] = $request->dateOfBirth ?? $request->date_of_birth;
            $validatedData['firstName'] = $customerName[0];
            // $validatedData['lastName'] = $customerName[1] ?? $customerName[0];
            $validatedData["customerEmail"] = $cust->customer_email;
            $validatedData["phoneNumber"] = $cust->customer_phone;
            $validatedData["idImage"] = $cust->customer_idFront;
            $validatedData["country"] = $cust->customer_country;
            $validatedData["city"] = $address['city'];
            $validatedData["state"] = $address['state'];
            $validatedData["zipCode"] = $address['zipcode'];
            $validatedData["line1"] = $address['street'];
            $validatedData["houseNumber"] = $address['number'];
            $validatedData["idType"] = "NATIONAL_ID";
            $validatedData["idNumber"] = $cust->customer_idNumber;
            if (isset($customerName[1]) && strlen($customerName[1]) >= 3) {
                $validatedData['lastName'] = $customerName[1];
            } elseif (isset($customerName[2]) && strlen($customerName[2]) >= 3) {
                $validatedData['lastName'] = $customerName[2];
            } else {
                $validatedData['lastName'] = $customerName[0];
            }
            $validatedData['userPhoto'] = $request->user_photo;


            // var_dump($validatedData); exit;

            // Call card API
            $req = $this->card->regUser($validatedData);
            if (!is_array($req)) {
                $req = (array)$req;
            }

            if (isset($req['errorCode']) && $req['errorCode'] >= 400) {
                return get_error_response(['error' => "Error, Please contact support."]);
            } elseif (isset($req['status']) && $req['status'] == true) {
                $cust->can_create_vc = true;
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
                'customer_id' => 'required|string',
                'amount' => 'required|numeric|min:5',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }


            $where = [
                'customer_id' => $request->customer_id,
                'user_id' => auth()->id()
            ];

            $cust = Customer::where($where)->first();

            if (!$cust) {
                return get_error_response(['error' => "Customer with the provided ID not found!"]);
            }

            // if ((bool)$cust->can_create_vc === false || $cust->vc_customer_id == null) {
            //     return get_error_response(['error' => "Customer not approved for this service"]);
            // }

            // debit user for card creation
            debit_user_wallet(settings('virtual_card_creation', 5), "USD", "Virtual Card Creation");

            // Ensure the customer_email field is available and correctly fetched
            if (!$cust->customer_email) {
                return get_error_response(['error' => "Customer email not found!"]);
            }

            $data = [
                'customerEmail' => $cust->customer_email,
                'cardBrand' => 'visa',
                'cardType' => 'virtual',
                'reference' => generate_uuid(),
                'amount' => $request->amount,
            ];

            // Check how many cards the customer has; max of 3 is allowed per customer
            $cardCount = CustomerVirtualCards::where([
                'customer_id' => $request->customer_id,
                'business_id' => get_business_id(auth()->id())
            ])->count();

            if ($cardCount >= 2) {
                return get_error_response(['error' => "Customer can create at most 3 cards"]);
            }

            $bitnob = new Bitnob();
            $cards = $bitnob->cards();
            $create = $cards->create($data);
            // var_dump($create); exit;
            if(!is_array($create)) {
                $create = (array)$create;
            }

            if (isset($create['status']) && $create['status'] === true) {
                // Save card details into DB, call get card to retrieve card details
                $cardId = $create['data']['id'];
                $getCard = self::show($cardId, true);
                Log::error("this is the card details: ", $getCard);

                if ($getCard && $save = $this->saveVirtualCard($getCard, $cardId, $request)) {
                    return get_success_response($save);
                }

                // Data to be returned
                $arr = [
                    "card_id" => $cardId,
                    "customer_email" => $cust->customer_email,
                    "customer_id" => $cust->customer_id,
                    "card_brand" => "visa",
                    "card_type" => "virtual",
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
            Log::error("Bitnob error:", $th->getTrace());
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function saveVirtualCard($card, $cardId, $request)
    {
        $virtualCard = new CustomerVirtualCards();
        $virtualCard->business_id = get_business_id(auth()->id());
        $virtualCard->customer_id = $request->customer_id;
        $virtualCard->customer_card_id = $cardId;
        $virtualCard->card_number = $card['cardNumber'];
        $virtualCard->expiry_date = $card['valid'];
        $virtualCard->cvv = $card['cvv2'];
        $virtualCard->card_id = $cardId;
        $virtualCard->raw_data = json_encode($card);

        if ($virtualCard->save()) {
            return $virtualCard->toArray();
        }

        return false; // <-- Add this line
    }


    public function show($cardId, $arrOnly = false)
    {
        try {
            $card = $this->card->getCard($cardId);

            if (empty($card)) {
                return $arrOnly ? null : get_error_response(['error' => "Card not found!"], 404);
            }

            if (!is_array($card)) {
                $card = (array) $card;
            }

            $arr = ["reference", "createdStatus", "customerId", "customerEmail", "status", "cardUserId", "createdAt", "updatedAt"];
            $arrData = [];
            // return response()->json($card);
            if (isset($card['data'])) {
                $arrData = $card['data'];

                foreach ($arr as $key) {
                    unset($arrData[$key]);
                }

                // handle error cases inside the data block
                if (isset($arrData['error']) || (isset($arrData['statusCode']) && (int)$arrData['statusCode'] === 500)) {
                    return $arrOnly ? null : get_error_response(['error' => $arrData['message']], $arrData['statusCode'] ?? 400);
                }
            }

            return $arrOnly ? $arrData : get_success_response($arrData);
        } catch (\Exception $e) {
            return $arrOnly
                ? null
                : get_error_response(['error' => env('APP_ENV') === 'local' 
                ? $e->getMessage() : 'Something went wrong, please try again later']);
        }
    }


    public function update(Request $request, $cardId)
    {
        try {
            $validate = Validator::make($request->all(), [
                "action" => "required|in:freeze,unfreeze"
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }


            $card = $this->card->action($request->action, $cardId);
            return get_success_response(['action' => $card['message']]);
        } catch (\Exception $e) {
            if(env('APP_ENV') == 'local') {
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
            if(env('APP_ENV') == 'local') {
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
                "cardId" => "required",
                "amount" => "required|numeric|min:1",
            ]);

            if ($validator->fails()) {
                return get_error_response($validator->errors()->toArray());
            }

            $customer = Customer::where('customer_id', $request->customer_id)
                ->where('user_id', auth()->id())
                ->first();

            if (!$customer) {
                return get_error_response(['error' => 'Invalid customer ID provided']);
            }

            $data = [
                'cardId' => $request->cardId,
                'reference' => generate_uuid(),
                'amount' => floatval($request->amount * 100),
            ];

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
            if(env('APP_ENV') == 'local') {
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
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $e->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }
}
