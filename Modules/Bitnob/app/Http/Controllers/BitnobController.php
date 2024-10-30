<?php

namespace Modules\Bitnob\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Modules\Bitnob\app\Models\VirtualCards;


class BitnobController extends Controller
{

    /**
     * Enable the customers to be 
     * able to create virtual cards from Bitnob
     *  
     */
    public function reg_user(Request $request)
    {
        try {
            $user = $request->user();
            $data = [
                'customerEmail' => $user->email,
                'idNumber' => $user->idNumber,
                'idType' => $user->idType,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'phoneNumber' => $user->phoneNumber,
                'city' => $user->city,
                'state' => $user->state,
                'country' => $user->country,
                'zipCode' => $user->zipCode,
                'line1' => $user->street,
                'houseNumber' => $user->houseNumber,
                'idImage' => $user->verificationDocument,
            ];
            $result = app('bitnob')->regUser($data);
            if ($result) {
                return get_success_response($result);
            }
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function createCard(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "customerId"    => "required|exists:customers,customer_id",
                'amount' => 'required',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $data = [
                'customerEmail' => $request->user()->email,
                'cardBrand' => 'visa', // cardBrand should be "visa" or "mastercard"
                'cardType' => 'virtual',
                'reference' => generate_uuid(),
                'amount' => $request->amount * 100,
            ];
            $result = app('bitnob')->create($data);

            if ($result) {
                return get_success_response($result);
            }
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function topupCard(Request $request, $cardId)
    {
        try {

            if (!self::card_exists($cardId)) {
                return get_error_response(['error' => 'Card not found']);
            }

            $arr = [
                'cardId' => $cardId,
                'reference' => generate_uuid(),
                'amount' => $request->amount,
            ];
            $result = app('bitnob')->topup($arr);
            if ($result) {
                return get_success_response($result);
            }
            
            
            return get_error_response(['error' => 'Please contact support']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Freeze or unfreeze a card
     * 
     * @return array|object
     */
    public function freeze_unfreeze($action, $cardId)
    {
        /**
         * Freeze or unfreeze card
         */
        try {
            if (!self::card_exists($cardId)) {
                return get_error_response(['error' => 'Card not found']);
            }

            if ($action != 'freeze' and $action != 'unfreeze')
                return get_error_response(['error' => 'Invalid action type']);
            $result = app('bitnob')->action($action, $cardId);
            if ($result) {
                return get_success_response($result);
            }

            return get_error_response(['error' => 'Please contact support']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Get all user virtual cards
     * 
     * @return array|object
     */
    public function getCards(Request $request)
    {
        try {
            $cards = VirtualCards::whereUserId(auth()->id())->paginate(per_page(10));
            return paginate_yativo($cards);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Get card details - cvv_number and co
     * 
     * @param string cardId
     * 
     * @return array|object
     */
    public function getCard($cardId)
    {
        try {
            if (!self::card_exists($cardId)) {
                return get_error_response(['error' => 'Card not found']);
            }
            $result = app('bitnob')->getCard($cardId);
            if ($result) {
                return get_success_response($result);
            }

            return get_error_response(['error' => 'Please contact support']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Get transaction history for cardId
     * 
     * @param string cardId
     * 
     * @return array|object
     */
    public function transactions(Request $request, $cardId)
    {
        try {
            // check if the user has/own the cardId
            if (!self::card_exists($cardId)) {
                return get_error_response(['error' => 'Card not found']);
            }


            $result = app('bitnob')->getTransaction($cardId);
            if ($result) {
                return get_success_response($result);
            }

            return get_error_response(['error' => 'Please contact support']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Check if card exists and card belong to user making the request
     * @param string $cardId
     * 
     * @return bool
     */
    private function card_exists($cardId)
    {
        $where = [
            'card_number' => $cardId,
            'user_id' => auth()->id()
        ];
        $cards = VirtualCards::where($where)->count();
        if ($cards < 1) {
            return false;
        }

        return true;
    }

    public function handleWebhook(Request $request)
    {
        //
    }
}