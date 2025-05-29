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
            $checkout = null;
            
            // First try to decrypt the ID
            try {
                $decryptedId = Crypt::decrypt($id);
                $checkout = CheckoutModel::findOrFail($decryptedId);
            } catch (DecryptException $e) {
                // If decryption fails, try direct matches
                $checkout = CheckoutModel::where('deposit_id', $id)
                    ->orWhere('checkouturl', $id)
                    ->first();
            }

            if (!$checkout) {
                abort(404, 'Invalid checkout ID provided or checkout record not found.');
            }

            // Check expiration
            if ($checkout->created_at->diffInHours(now()) >= 24) {
                $checkout->update(['checkout_status' => 'expired']);
                abort(403, 'This checkout link has expired.');
            }

            // Check if already used
            // if ($checkout->checkout_status === 'used') {
            //     abort(403, 'This checkout has already been used.');
            // }

            // Mark as used
            $checkout->update(['checkout_status' => 'used']);

            return view('checkout.index', compact('checkout'));

        } catch (ModelNotFoundException $e) {
            abort(404, 'Invalid checkout ID provided or checkout record not found.');
        }
    }
}
