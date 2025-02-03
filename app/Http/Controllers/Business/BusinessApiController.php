<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Beneficiary\app\Models\Beneficiary;

class BusinessApiController extends Controller
{
    public function getBeneficiaries()
    {
         try {
            $beni = Beneficiary::whereUserId(auth()->id())->latest()->paginate(per_page(10));
            return paginate_yativo($beni);
         } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
         }
    }

    public function addBeneficiary(Request $request)
    {
        try {
            
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
