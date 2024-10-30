<?php

namespace Modules\ShuftiPro\app\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\ShuftiPro\app\Models\ShuftiPro;

class ShuftiProServices
{

    /**
     * generate the shuftipro reference and 
     * verification url for the user
     * 
     * @return \Response | string
     */
    public function init(Request $request)
    {
        try {
            //Shufti Pro API base URL
            $user = User::find(active_user());

            $max_attempts = 3;
            $user_id = $user->user_id;
            $cache_key = 'shufti_attempt_' . $user_id;
            cache()->forget($cache_key);
            $attempt_count = cache()->get($cache_key, 0);

            if ($attempt_count >= $max_attempts) {
                // Maximum attempts reached, return an error response
                return get_error_response(['error' => 'You have reached the maximum allowed number of verifications. Please contact support.']);
            }

            $attempt_count++;
            cache()->forever($cache_key, $attempt_count);

            $response = $this->getShuftiUrl($user);

            if (isset($response["error"])) {
                return get_error_response(['error' => $response['error']['message']]);
            }

            ShuftiPro::create([
                'user_id' => $user->id,
                'reference' => $response['reference'],
                'status' => 'pending',
                'payload' => $response
            ]);

            if (isset($response['verification_url'])) {
                $url = $response['verification_url'];
            }
            return get_success_response(['url' => $url]);

        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Callback function for Shufti Pro
     * to generate the verification link
     * 
     * @return  array
     */
    public function getShuftiUrl($user)
    {
        $ref_code = generate_uuid();
        $verification_request = [
            "reference" => $ref_code,
            "journey_id" => "NrTOXhlR1720800102",
            "email" => $user->email,
            'callback_url' => env("WEB_URL", "https://app.yativo.com")
        ];
        $post_data = json_encode($verification_request);

        $url = 'https://api.shuftipro.com/';
        $client_id = getenv('SHUFTI_PRO_CLIENT_ID');
        $secret_key = getenv('SHUFTI_PRO_SECRET_KEY');

        $auth = $client_id . ":" . $secret_key;
        $headers = ['Content-Type: application/json'];
        $response = $this->api_call($url, $post_data, $headers, $auth);

        return $response;
    }

    public function businessVerification($businessName, $businessRegistrationNumber, $businessJurisdictionCode, $businessCountry)
    {
        try {
            //Shufti Pro API base URL
            $url = 'https://api.shuftipro.com/';
            $client_id = getenv('SHUFTI_PRO_CLIENT_ID');
            $secret_key = getenv('SHUFTI_PRO_SECRET_KEY');
            $user = User::find(active_user());

            $auth = $client_id . ":" . $secret_key;

            $ref_code = generate_uuid();
            $verification_request = [
                'reference' => $ref_code,
                'country' => $businessCountry,
                'language' => 'EN',
                'email' => $user->email,
                'callback_url' => 'https://yourdomain.com/profile/notifyCallback',
                'kyb' => [
                    'company_registration_number' => $businessRegistrationNumber,
                    'company_jurisdiction_code' => $businessJurisdictionCode,
                    'company_name' => $businessName
                ],
            ];

            $auth = base64_encode($client_id . ":" . $secret_key);

            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $auth,
            ];

            $response = Http::withHeaders($headers)->post($url, $verification_request);
            $response_data = $response->body();
            $response_headers = $response->headers();

            $sp_signature = $response_headers['Signature'] ?? $response_headers['signature'] ?? null;

            $calculate_signature = hash('sha256', $response_data . $secret_key);
            $decoded_response = json_decode($response_data, true);
            $event_name = $decoded_response['event'] ?? null;

            if (in_array($event_name, ['verification.accepted', 'verification.declined', 'request.received'])) {
                if ($sp_signature === $calculate_signature) {
                    echo $event_name . " :" . $response_data;
                } else {
                    echo "Invalid signature :" . $response_data;
                }
            } else {
                echo "Error :" . $response_data;
            }


            return get_success_response(['url' => $response]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function callback(Request $request)
    {
        // Log the incoming request for debugging
        \Log::info('ShuftiPro Webhook Initiated:- ', $request->all());
        try {
            if ($request->has('event') && $request->event == "verification.accepted") {
                // decode event and update user status using the user email as identifier

                // Log the incoming request for debugging
                \Log::info('ShuftiPro Webhook Received: ', $request->all());

                // Validate the incoming request (you can add more validation as needed)
                $validate = Validator::make($request->all(), [
                    'event' => 'required|string',
                    'reference' => 'required|string',
                    'verification_result' => 'required|array',
                    // add other necessary fields
                ]);

                if ($validate->fails()) {
                    return get_error_response(['error' => $validate->errors()->toArray()]);
                }
    

                // Extract data from the request
                $event = $request->event;
                $reference = $request->reference;
                $verificationResult = $request->verification_result;

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



                // $signature = $request->header('x-shuftipro-signature');
                // $secretKey = config('services.shuftipro.secret'); // Make sure to store this in your config/services.php

                // $payload = $request->getContent();
                // $computedSignature = hash_hmac('sha256', $payload, $secretKey);

                // if (!hash_equals($signature, $computedSignature)) {
                //     return response()->json(['message' => 'Invalid signature'], 400);
                // }


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

                return get_error_response(["status" => 'Verification completed successfully']);
            }
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    /**
     * Get headers from curl response
     * 
     * @return array
     */
    public function get_header_keys($header_string)
    {
        $headers = [];
        $exploded = explode("\n", $header_string);
        if (!empty($exploded)) {
            foreach ($exploded as $key => $header) {
                if (!$key) {
                    $headers[] = $header;
                } else {
                    $header = explode(':', $header);
                    $headers[trim($header[0])] = isset($header[1]) ? trim($header[1]) : "";
                }
            }
        }

        return $headers;
    }

    /**
     * Make curl request to Shufti Pro API
     * 
     * @return array
     */
    public function api_call($url, $post_data, $headers, $auth)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERPWD, $auth);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $html_response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($html_response, 0, $header_size);
        $body = substr($html_response, $header_size);
        curl_close($ch);

        if (!is_array($body)) {
            $body = json_decode($body, true);
        }

        return $body;
    }
}
