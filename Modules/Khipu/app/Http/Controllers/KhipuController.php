<?php

namespace Modules\Khipu\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Khipu\app\Services\KhipuServices;

class KhipuController extends Controller
{
    public function webhook(Request $request)
    {
        // Get raw JSON payload (body of the request)
        $jsonPayload = file_get_contents('php://input');

        $khipu = new KhipuServices();
        if($khipu->webhook($jsonPayload)) {
            // process webhook as it's valid
        }
    }
}
