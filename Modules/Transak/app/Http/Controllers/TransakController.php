<?php

namespace Modules\Transak\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use app\Services\TransakServices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TransakController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $transactions = Transaction::where('payment_method', 'transak')->orderBy("created_at","desc")->paginate(10);
            return paginate_yativo($transactions);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $core = new TransakServices();
            $checkout_url = $core->create_url($request->post());
            return $checkout_url;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function validateWalletAddress(Request $request) 
    {
        try {
            $core = new TransakServices();
            $checkout_url = $core->validateWallet($request->wallet_address, $request->coin_ticker, $request->coin_network);
        } catch (\Throwable $th) {}
    }
}
