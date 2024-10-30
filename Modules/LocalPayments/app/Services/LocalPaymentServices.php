<?php

namespace Modules\LocalPayments\app\Services;

use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Currencies\app\Models\Currency;

class LocalPaymentServices
{

	public function buildPayinBankTransfer()
	{
		//
	}

	/**
	 * build the payment array info
	 * @return array
	 */
	public function buildPayoutBankTransfer($amount, $currency, $quoteId, $beneficiary)
	{
		try {
			$request = request();
			$model = new BeneficiaryPaymentMethod();
			$beneficiary = $model->getBeneficiaryPaymentMethod($beneficiary);

			if (!is_array($beneficiary)) {
				$beneficiary = json_decode($beneficiary, true);
			}

			if (!isset($beneficiary['payment_data'])) {
				return ['error' => 'Invalid beneficiary data'];
			}

			$paymentData = $beneficiary['payment_data'];

			if (!isset($paymentData['beneficiary'])) {
				return ['error' => 'paymentData data not found'];
			}

			$beneficiaryData = $paymentData['beneficiary'];
			$merchant = $paymentData['merchant'] ?? null;
			$sender = $paymentData['sender'] ?? [];

			$bank = $beneficiaryData['bank'];
			$document = $beneficiaryData['document'] ?? [];

			if (is_numeric($currency)) {
				$curr = Currency::find($currency);
				$currency = $curr->wallet;
			}

			$payout = get_payout_code($currency);

			if (empty($payout) || $payout == null) {
				return ['error' => 'Please contact support with error code: NoPayCodeFound' . \Str::random(5)];
			}

			$result = [
				'externalId' => $quoteId,
				'country' => $payout['iso3'] ?? null,
				'currency' => $currency,
				'amount' => $amount,
				'paymentMethod' => [
					'type' => 'BankTransfer',
					'code' => $payout['code'] ?? null,
					'flow' => 'direct',
				],
				'beneficiary' => [
					'name' => $beneficiaryData['name'] ?? null,
					'lastName' => $beneficiaryData['lastName'] ?? $beneficiaryData['lastname'] ?? $beneficiaryData['LastName'] ?? $beneficiaryData['LASTNAME'] ?? null,
					'type' => isset($beneficiaryData['type']) ? strtoupper($beneficiaryData['type']) : null,
					'document' => [
						'type' => $document['type'] ?? null,
						'id' => $document['id'] ?? null,
					],
					'bank' => [
						'name' => $bank['name'] ?? null,
						'code' => $bank['code'] ?? null,
						'account' => [
							'number' => $bank['account']['number'] ?? null,
							'type' => $bank['account']['type'] ?? null,
						],
					],
				],
				'merchant' => [
					'type' => $merchant['type'] ?? 'COMPANY',
					'name' => $merchant['name'] ?? 'Yativo',
				],
				'accountNumber' => $payout['accountNumber'] ?? null,
				'conceptCode' => $paymentData['conceptCode'] ?? null,
			];

			$arr = ["paymentMethod", "beneficiary", "merchant"];
			foreach ($arr as $key) {
				if (!array_key_exists($key, $result)) {
					return ['error' => "$key is missing from payload"];
				}
			}

			return $result;
		} catch (\Throwable $th) {
			return ['error' => $th->getMessage()];
		}
	}
}
