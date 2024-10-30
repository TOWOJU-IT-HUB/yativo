<?php

namespace Modules\ShuftiPro\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\ShuftiPro\app\Models\ShuftiPro;

class ShuftiProWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        // Log the incoming request for debugging
        \Log::channel('shufti')->info('ShuftiPro Webhook Received: ', $request->all());

        // Validate the incoming request (you can add more validation as needed)
        $this->validate($request, [
            'event' => 'required|string',
            'reference' => 'required|string',
            'verification_result' => 'required|array',
            // add other necessary fields
        ]);

        // Extract data from the request
        $event = $request->input('event');
        $reference = $request->input('reference');
        $verificationResult = $request->input('verification_result');

        switch ($event) {
            case 'verification.accepted':
                $new_status = 'approved';
                break;

            case 'verification.declined':
                $new_status = 'rejected';
                break;
            case 'verification.cancelled':
                $new_status = 'pending';
                break;

            default:
                $new_status = 'pending';
                break;
        }

        // Log the processed data for debugging
        \Log::info('ShuftiPro Webhook Processed: ', [
            'event' => $event,
            'reference' => $reference,
            'verification_result' => $verificationResult,
        ]);

        $shufti = ShuftiPro::where('reference', $reference)->first();
        if ($shufti) {
            $user = User::find($shufti->user_id);
            $user->kyc_status = $new_status;
            $user->is_kyc_submitted = $new_status == 'approved' ? true : false;
            $user->save();

            $shufti->status = $new_status;
            $shufti->save();
        }


        $signature = $request->header('x-shuftipro-signature');
        $secretKey = config('services.shuftipro.secret'); // Make sure to store this in your config/services.php

        $payload = $request->getContent();
        $computedSignature = hash_hmac('sha256', $payload, $secretKey);

        if (!hash_equals($signature, $computedSignature)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Respond with a 200 status code to acknowledge receipt
        return response()->json(['message' => 'Webhook received'], 200);
    }
}
