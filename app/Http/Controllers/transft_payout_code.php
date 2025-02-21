<?php

$customer = $this->getCustomerInfo();
$payoutId = "Yativo-" . rand(2039, 999101);
$amount = 430;
try {
    $payload = [
        "email" => $customer->customer_email ?? $customer->email,
        "currency" => "THB", //strtoupper($currency),
        "amount" => $amount,
        "paymentCode" => "siam_commercial_bank", //$payoutObj['paymentCode'],
        "paymentAccountNumber" => "7072835580", //$payoutObj['paymentAccountNumber'],
        "purposeCode" => $payoutObj['purposeCode'] ?? "other",
        "partnerContext" => [
            "payout_id" => $payoutId,
            "payout_amount" => $amount,
            "order_type" => "payout",
            "payout_object" => []
        ],
        "additionalDetails" => [
            "accountNumber" => "7072835580",
        ],
        "partnerId" => $payoutId,
        "depositDetails" => [
            "cryptoTicker" => "USDTBSC"
        ]
    ];

    $response = Http::withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . base64_encode("$this->apiKey:$this->apiSecret"),
        'Content-Type' => 'application/json',
    ])->post($this->apiUrl . '/payout/orders', $payload);

    if ($response->successful()) {
        $result = $response->json();
        if (isset($result['orderId'])) {
            return ["message" => "Payout initiated successfully"];
        }
    } else {
        $result = [
            'error' => $response->json(),
        ];
    }
} catch (\Exception $e) {
    return ['error' => $e->getMessage()];
}

var_dump($result);
exit;