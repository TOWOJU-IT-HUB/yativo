<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaxosController extends Controller
{
    public function processPexos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            //
        ]);
    }
}
