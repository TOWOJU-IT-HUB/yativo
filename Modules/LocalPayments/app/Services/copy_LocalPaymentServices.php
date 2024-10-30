<?php


namespace Modules\LocalPayments\app\Services;

use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Currencies\app\Models\Currency;

class copy_LocalPaymentServices
{

	public function buildPayinBankTransfer()
	{
		$array = [
			'paymentMethod' => [
				'type' => 'BankTransfer',
				'code' => '1313',
				'flow' => 'DIRECT',
			],
			'externalId' => 'test_01',
			'country' => 'BRA',
			'amount' => 1000,
			'currency' => 'BRL',
			'exchangeRateToken' => '76b54005-01a4-4b0c-8cd8-c51d31828b43',
			'accountNumber' => '076.986.00000001',
			'conceptCode' => '0003',
			'comment' => 'Add any relevant information related to the transaction',
			'merchant' => [
				'type' => 'INDIVIDUAL',
				'name' => 'Merchant\'s name',
				'lastname' => 'Merchant\'s last name',
				'document' => [
					'type' => '',
					'id' => '',
				],
				'email' => '',
			],
			'payer' => [
				'type' => 'INDIVIDUAL',
				'name' => 'Payer\'s name',
				'lastname' => 'Payer\'s last name',
				'document' => [
					'id' => '11.111.111/0001-56',
					'type' => 'CNPJ',
				],
				'email' => 'payer@email.com',
				'bank' => [
					'name' => 'Account holder name',
					'code' => '341',
					'branch' => [
						'code' => '1234',
						'name' => 'ITAU',
					],
					'account' => [
						'type' => 's',
						'number' => '123456',
					],
				],
			],
		];
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

			// echo json_encode($beneficiary, JSON_PRETTY_PRINT); exit;
			// $beneficiary = $request->beneficiary;
			$countryJsons = file_get_contents(public_path('banks/our-currencies.json'));
			$countryArray = json_decode($countryJsons, true);

			$countries = $countryArray['accounts'];
			$data = (object) $countries;

			$beneficiaryData = $beneficiary->payment_data->beneficiary;
			$merchant = $beneficiary->payment_data?->merchant;
			$sender = $beneficiary?->payment_data?->sender ?? "{}";

			$bank = $beneficiaryData->bank;
			$document = $beneficiaryData?->document;

			if(is_numeric($currency)) {
				$curr = Currency::find($currency);
				$currency = $curr->wallet;
			}

			$array = [
				'externalId' => $quoteId,
				'country' => $beneficiary->payment_data?->country,
				'currency' => $currency,
				'amount' => $amount,
				'paymentMethod' => [
					'type' => 'BankTransfer',
					'code' => $beneficiary->payment_data->paymentMethod->code,
					'flow' => 'direct',
				],
				'beneficiary' => [
					'name' => $beneficiaryData->name,
					'lastName' => $beneficiaryData->lastName,
					'type' => $beneficiaryData->type,
					'document' => [
						'type' => $document->type ?? 'N/A',
						'id' => $document->id ?? 'N/A',
					],
					'bank' => [
						'name' => $bank?->name ?? 'N/A',
						'code' => $bank?->code ?? 'N/A', //'025',
						'account' => [
							'number' => $bank?->account->number ?? 'N/A',
							'type' => $bank?->account->type ?? 'N/A', //'S',
						],
					],
				],
				'merchant' => [
					'type' => $merchant?->type ?? 'COMPANY',
					'name' => $merchant?->name ?? 'Yativo',
				],
				'accountNumber' => $beneficiary->payment_data->accountNumber ?? 'N/A',
				'conceptCode' => $beneficiary->payment_data->conceptCode,
			];

			echo json_encode($array); exit;
			return $array;
		} catch (\Throwable $th) {
			return (['error' => $th->getMessage()]);
		}
	}

}