<?php

namespace App\Http\Controllers;

use App\Models\CheckoutModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class CheckoutController extends Controller
{
    public function show($id)
    {
        // Decrypt the ID before using it
        $decryptedId = Crypt::decrypt($id);

        // Find the checkout record by the decrypted deposit ID
        $checkout = CheckoutModel::whereId($decryptedId)->firstOrFail();

        // Pass the checkout data to the view
        return view('checkout.index', compact('checkout'));
    }
}
