<?php

namespace Modules\ShuftiPro\app\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Ex_ShuftiProServices
{
    public function init(Request $request)
    {
        try {
            $customer = User::finf(active_user());
            $url = 'https://api.shuftipro.com/';
 
            $client_id  = getenv('SHUFTI_PRO_CLIENT_ID');
            $secret_key = getenv('SHUFTI_PRO_SECRET_KEY');

            $verification_request = [
                'reference'    => strtoupper(Str::random(8)),
                'country'      => $customer->country,
                'language'     => 'EN',
                'email'        => $customer->email,
                'callback_url' =>  'route()',
                'verification_mode' => 'any',
                'ttl'           => 60,
            ];

            $verification_request['document'] = [
                'proof' => '',
                'additional_proof' => '',
                'name' => '',
                'dob'             => $customer->dob,
                'age'             => $customer->age,
                'document_number' => '',
                'expiry_date'     => '',
                'issue_date'      => '',
                'allow_offline'      => '1',
                'allow_online'     => '1',
                'supported_types' => ['id_card', 'passport'],
                'gender'   => $customer->gender
            ];

            $verification_request['address'] = [
                'proof' => $customer->addressProof,
                'name' => $customer->name,
                'full_address'    => $customer->address,
                'address_fuzzy_match' => '1',
                'issue_date' => '',
                'supported_types' => ['utility_bill', 'passport', 'bank_statement']
            ];

            $auth = $client_id . ":" . $secret_key;
            $headers = ['Content-Type: application/json'];

            $post_data = json_encode($verification_request);
            $response = self::api_call($url, $post_data, $headers, $auth);
            $response_data    = $response['body'];
            $exploded = explode(" ", $response['headers']);
            $sp_signature = null;
            foreach ($exploded as $key => $value) {
                if (strpos($value, 'signature: ') !== false || strpos($value, 'Signature: ') !== false) {
                    $sp_signature = trim(explode(':', $exploded[$key])[1]);
                    break;
                }
            }

            $calculate_signature  = hash('sha256', $response_data . $secret_key);
            $decoded_response = json_decode($response_data, true);
            $event_name = $decoded_response['event'];

            if ($event_name == 'request.pending') {
                if ($sp_signature == $calculate_signature) {
                    $verification_url = $decoded_response['verification_url'];
                    return ["error" =>  "Verification url :" . $verification_url];
                } else {
                    return ["error" => "Invalid signature :" . $response_data];
                }
            } else {
                return ["error" => $response_data];
            }
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function callback(Request $request)
    {
        try {
            //code...
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

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
        return ['headers' => $headers, 'body' => $body];
    }
}
