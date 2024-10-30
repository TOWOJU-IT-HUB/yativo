<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Validator;

class OtpController extends Controller
{
    /**
     * Send OTP to the provided phone number via WhatsApp
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function send(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'phone' => 'required|numeric',
        ], [
            'phone.required' => 'Phone Number is required!',
            'phone.numeric' => 'Invalid Phone Number provided!',
        ]);

        if ($validate->fails()) {
            return get_error_response(['error' => $validate->errors()->toArray()]);
        }

        $resp = [
            'channel'       => 'whatsapp',
            'sender_id'     => getenv("DOJA_SENDER_ID"), //$request->sender_id,
            'destination'   => $request->phone
        ];


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, getenv('DOJA_BASE_URL') . "messaging/otp");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($resp));
        $headers = array();
        $headers[] = 'Appid: ' . getenv("DOJA_APP_ID");
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Authorization: ' . getenv("DOJA_PRIVATE_KEY");

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);

        if (empty($result)) {
            return get_error_response(['error' => 'Unable to send OTP'], 400);
        }

        if (!is_array($result) && !empty($result)) {
            $result = json_decode($result, true);
        }
        // return response()->json($result);

        if (!empty($result) && array_key_exists('entity', $result)) {
            $data = [
                'status'    => 'success',
                'code'      =>  http_response_code(),
                'message'   =>  "O.T.P Sent Successfully",
                'data'      =>  $result['entity'][0]
            ];
        } else {
            $data = get_error_response($result, 400);
        }
        curl_close($ch);
        return response()->json($data);
    }


    /**
     * Validate the OTP code provided by the user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'reference_id' => 'required',
            'code' => 'required'
        ]);

        if ($validate->fails()) {
            return get_error_response(['error' => $validate->errors()->toArray()]);
        }

        $data = [];
        $resp = [
            'code' => $request->code,
            'reference_id' => $request->reference_id
        ];

        // Http post request 
        $endpoint = getenv('DOJA_BASE_URL') . "/messaging/otp/validate?code={$request->code}&reference_id={$request->reference_id}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        //curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $headers = array();
        $headers[] = 'Appid: ' . getenv("DOJA_APP_ID");
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Authorization: ' . getenv("DOJA_PRIVATE_KEY");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (!is_array($result)) {
            $result = json_decode($result, true);
        }
        // return response()->json($result);

        if (!empty($result) && array_key_exists('valid', $result['entity'])) {
            $mainResult = result($result['entity']);
            if ($mainResult['valid'] == true) {
                $data = [
                    'status' => 'success',
                    'code' => http_response_code(),
                    'message' => "O.T.P verified Successfully",
                    'data' => [
                        "message" => "O.T.P verified successfully",
                    ]
                ];
                return get_success_response($data);
            } else {
                $data = get_error_response($result['entity'], 400)->original;
            }
        } else {
            $data = get_error_response($result['entity'], 400)->original;
        }

        return $data;
    }
}
