<?php

namespace App\Http\Controllers;

use App\Models\CheckoutModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CheckoutController extends Controller
{
    public function show($id)
    {
        \Log::info('Incoming ID for checkout:', ['id' => $id]);

        try {
            // Attempt to decrypt the ID and retrieve the checkout record
            $decryptedId = Crypt::decrypt($id);
            $checkout = CheckoutModel::findOrFail($decryptedId);
        } catch (DecryptException | ModelNotFoundException $e) {
            // If decryption or finding fails, attempt direct match with checkouturl or abort
            $checkout = CheckoutModel::where('checkouturl', $id)->first();
            if (!$checkout) {
                abort(404, 'Invalid checkout ID provided or checkout record not found.');
            }
        }

        return view('checkout.index', compact('checkout'));
    }
}
