<?php

namespace Modules\SendMoney\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DepositController;
use App\Jobs\Transaction;
use App\Models\Business;
use App\Models\Deposit;
use App\Models\Gateways;
use App\Models\PayinMethods;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Services\DepositService;
use App\Services\PaymentService;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\SendMoney\app\Models\ApiQuote;
use Modules\SendMoney\app\Models\QuoteExtra;
use Modules\SendMoney\app\Models\SendMoney;
use Modules\SendMoney\app\Models\SendQuote;
use Modules\SendMoney\app\Notifications\SendMoneyNotification;
use Modules\SendMoney\app\Notifications\SendMoneyQuoteNotification;


class SendMoneyController extends Controller
{
	public const SUCCESS = "success";
	public const FAILED = "failed";
	public const REVERSED = "reversed";
	public const CANCELLED = "cancelled";


	public function __construct()
	{
		// $this->middleware('chargeWallet')->only('send_money');
	}


	public function get_quotes()
	{
		try {
			$quotes = SendQuote::whereUserId(active_user())->with('details')->latest()->paginate(10);
			return paginate_yativo($quotes);
		} catch (\Throwable $th) {
			get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
		}
	}

	public function get_quote($id)
	{
		try {
			$quotes = SendQuote::with('purpose')->whereUserId(active_user())->whereId($id)->first();
			return get_success_response($quotes);
		} catch (\Throwable $th) {
			get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
		}
	}

	public function gateways(Request $request)
	{
		/**
		 * @param string currency : Currency the customer is sending in
		 * @param string action : Which action is customer performing Ex: deliver_by(receiver received by) or pay_by(charge sender)
		 */
		try {
			$validate = Validator::make($request->all(), [
				'currency' => 'required',
				'action' => 'required|in:deliver_by,pay_by'
			]);

			if ($validate->fails()) {
				return get_error_response(['error' => $validate->errors()->toArray()]);
			}


			if ($request->action == 'deliver_by') {
				$gateways = Gateways::where(['status' => true, 'payout' => true])->whereJsonContains('payout_currencies', $request->currency)->get();
			}

			if ($request->action == 'pay_by') {
				$gateways = Gateways::where(['status' => true, 'deposit' => true])->whereJsonContains('payin_currencies', $request->currency)->get();
			}
			return get_success_response($gateways);
		} catch (\Throwable $th) {
			get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
		}
	}

	public function create_quote(Request $request)
	{
		try {
			$validate = Validator::make(
				$request->all(),
				[
					'send_amount' => 'required', // Amount to Receive by beneficiary
					'send_gateway' => 'required', // ID of the deposit/payin method
					'beneficiary_id' => 'sometimes', // payment beneficiary ID
					'payment_method_id' => 'required', // ID of the beneficiary payment method 
					'transfer_purpose' => 'sometimes',
				]
			);

			if ($validate->fails()) {
				return get_error_response(['error' => $validate->errors()->toArray()]);
			}

			$validate = $validate->validated();

			$where = [
				"user_id" => auth()->id(),
				"id" => $request->payment_method_id
			];

			$beneficiary = BeneficiaryPaymentMethod::where($where)->first();

			if (!$beneficiary) {
				return get_error_response(['error' => "Beneficiary not found"]);
			}

			$send_gateway = PayinMethods::whereId($request->send_gateway)->first();

			if (!$send_gateway) {
				return get_error_response(['error' => "Invalid send method provided"]);
			}

			$beneficiary_gateway = getGatewayById($beneficiary->gateway_id);
			$beneficiary_currency = $beneficiary->currency;

			if (!$beneficiary_gateway) {
				return get_error_response(["error" => "Receiving gateway not found or currenctly unavailable"]);
			}

			if (!$beneficiary_currency) {
				return get_error_response(["error" => "Currency not found or currenctly unavailable"]);
			}

			$exchange_rate = floatval(get_transaction_rate($send_gateway->currency, $beneficiary_currency, $beneficiary->gateway_id, "payin"));

			// add user IP address to request
			$request->merge([
				'ip_address' => $request->ip(),
				'exchange_rate' => $exchange_rate
			]);

			$user = User::find(active_user());
			$transaction_fee = 0;
			$validate['rate'] = $exchange_rate;
			$validate['user_id'] = active_user();
			$validate['total_amount'] = $request->send_amount + $transaction_fee;
			$validate['raw_data'] = $request->all();

			if (SendQuote::get()->count() < 1) {
				$validate['id'] = '2111';
			}

			// calulate quote fees

			$validate['receive_gateway'] = $beneficiary_gateway;
			$validate['receive_currency'] = $beneficiary_currency;


			if ($send = SendQuote::create($validate)) {
				// add transaction history
				// @dispatch(new SendMoneyQuoteNotification($send, 'send_money'));
				$result = SendQuote::with('beneficiary', 'send_gateway')->whereId($send->id)->first()->toArray();

				$arr = array_merge($result, ['currency' => $beneficiary_currency], ['payout_info' => $beneficiary_gateway]);

				$payout_amount = floatval($request->send_amount * $exchange_rate);

				$arr['payment_info'] = [
					"send_amount" => $request->send_amount,
					"exchange_rate" => "1" . strtoupper($send_gateway->currency) . " ~ $exchange_rate" . strtoupper($beneficiary_currency),
					"transaction_fee" => $transaction_fee,
					"payout_amount" => $payout_amount,
					"payin_method" => $result['send_gateway']['method_name'],
					"payout_method" => $beneficiary_gateway["method_name"],
					"estimate_delivery_time" => formatSettlementTime($result['send_gateway']['settlement_time'] + $beneficiary_gateway["estimated_delivery"]),
					"total_amount_due" => $validate['total_amount']
				];
				$send->raw_data = $arr;
				$send->save();

				$send = result($send);

				$final = array_merge($arr, (array) $send);
				if (array_key_exists('error', $final)) {
					return get_error_response(['error' => $arr['error']]);
				}
				return get_success_response($final);
			}

			return get_error_response(['error' => 'Unable to process send request please contact support']);
		} catch (\Throwable $th) {
			return get_error_response(['error' => $th->getTrace()]);
		}
	}

	public function add_purpose(Request $request)
	{
		try {
			$validator = Validator::make($request->all(), [
				"quote_id" => "required|string",
				"transfer_purpose" => "required",
				"transfer_memo" => "sometimes|string",
				"attachment" => "sometimes|string",
				"metadata" => "sometimes|array",
			]);

			if ($validator->fails()) {
				return get_error_response($validator->errors()->toArray());
			}

			$params = QuoteExtra::firstOrCreate($validator->validated());

			return get_success_response($params->with('quote')->first()->toArray());
		} catch (\Throwable $th) {
			if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
		}
	}

	public function send_money(Request $request)
	{
		try {
			$validate = $validate = Validator::make($request->all(), [
				'quote_id' => 'required',
				'recipient_memo' => 'sometimes|string',
				'document' => 'sometimes|file:pdf,jpg,png,jpeg',
				'purpose_of_payment' => 'sometimes|string',
			]);

			if ($validate->fails()) {
				return get_error_response(['error' => $validate->errors()->toArray()]);
			}
			$validate = $validate->validated();

			$get_quote = SendQuote::whereUserId(active_user())->whereId($request->quote_id)->with(['send_gateway'])->first();

			$user = User::find(active_user());
			if (!isset($user->idType) || empty($user->id)) {
				return get_error_response(['error' => "Please submit your verification document to be able to use this payment method"]);
			}
			
			if ($get_quote) {
				@$user->notify(new SendMoneyNotification($get_quote));
				$validate['status'] = 'pending';
				$send = SendMoney::create($validate);
				if ($send) {
					$process_deposit = new DepositController;

					$payin = PayinMethods::whereId($get_quote->send_gateway)->first();
					$paymentUrl = $process_deposit->process_store($get_quote->send_gateway, $payin->currency, $get_quote->total_amount, $send->toArray(), 'send_money');
					if (is_object($paymentUrl)) {
						$paymentUrl = json_decode($paymentUrl, true);
					}
					if (is_null($paymentUrl) || empty($paymentUrl)) {
						return get_error_response(['error' => "Payment link generation failed"]);
					}

					if ((is_array($paymentUrl) && isset($paymentUrl['error']) && $paymentUrl['error'] != "ok")) {
						return get_error_response(['error' => $paymentUrl['error'] ?? "Unable to generate payment link"]);
					}

					return get_success_response(['link' => $paymentUrl, 'quote' => $get_quote]);
				}
				return get_error_response(['error' => 'Unable to process send request please contact support']);
			}

			return get_error_response(['error' => 'Quote not found or expired']);
		} catch (\Throwable $th) {
			get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
		}
	}

	public function complete_send_money($quoteId)
	{
		try {
			$send_money = SendMoney::whereQuoteId($quoteId)->first();
			$send_money->status = 'successful';
			$send_money->save();

			$quote = SendQuote::whereId($quoteId)->first();
			$quote->status = 'successful';
			$quote->save();

			// Inititate payout proccess
			$payout = new PayoutService();
			$init_payout = $payout->makePayment($quoteId, $quote->receive_gateway);
		} catch (\Throwable $th) {
			//throw $th;
		}
	}

	/**
	 * method = payment gateway
	 * mode ['deposit', 'payout']
	 */
	public function method_exists($method, $currency, $mode)
	{
		$where = [
			'slug' => $method,
			$mode => true
		];

		if ($mode == 'deposit')
			$process_mode = "payin_currencies";
		if ($mode == 'payout')
			$process_mode = "payout_currencies";
		$gate = Gateways::where($where)->whereJsonContains($process_mode, $currency)->count();
		return $gate;
	}

	/**
	 * Summary of bulk_send
	 * @param \Illuminate\Http\Request $request
	 * @return void
	 */
	public function bulk_send(Request $request)
	{
		try {
			$validator = Validator::make($request->all(), [
				'csv_file' => 'required|file:csv',
				'currency' => 'required|string',
				'debit_currency' => 'required|string',
				'gateway_id' => 'required|integer',
			]);

			if ($validator->fails()) {
				return get_error_response(['error' => $validator->errors()->toArray()]);
			}

			// Loop through CSV file and initiate a bulk payout request to the Payout services
			$csv_file = $request->file('csv_file');
			$csv_file_data = file_get_contents($csv_file);
			$csv_file_data = explode("\n", $csv_file_data);
			$csv_file_data = array_filter($csv_file_data);
			$csv_file_data = array_map('str_getcsv', $csv_file_data);

			// Assuming the first row is the header, remove it
			$header = array_shift($csv_file_data);

			$business = Business::where('user_id', active_user())->first();

			foreach ($csv_file_data as $row) {
				// Assuming the columns are in the order: customer_id, beneficiary_id, amount
				$customerId = $row[0];
				$beneficiaryId = $row[1];
				$amount = $row[2];
				// Find the corresponding quote

				$where = [
					'business_id' => $business->id,
					'customer_id' => $customerId
				];

				$quote = ApiQuote::where($where)->first();

				if ($quote) {
					$payout = new PayoutService();
					$init_payout = $payout->makePayment($quote->id, $quote->receive_gateway, $amount);
				}

				return get_success_response($init_payout); //['message' => 'Bulk payout request initiated']);
			}
		} catch (\Throwable $th) {
			if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
		}
	}


	/**
	 * @param mixed $tranxRecord  of deposit
	 * @return void
	 */
	public function process_deposit(TransactionRecord $tranxRecord)
	{
		try {
			$order = $tranxRecord;
			if ($order['transaction_status'] == 'success') {
				http_response_code(200);
				abort(404, 'Transaction already processed');
			} else {
				$payin = payinMethods::where('id', $order['gateway_id'])->first();
				$currency = $payin->currency;

				$deposit = Deposit::whereId($order['transaction_id'])->where('status', 'pending')->first();
				$deposit->status = SendMoneyController::SUCCESS;
				$deposit->save();

				$user = User::findOrFail($order['user_id']);
				$order->update(['transaction_status' => 'success']);
				// top up the customer 
				$wallet = $user->getWallet($currency);
				$wallet->credit($order['transaction_amount']);
			}

			Track::create([
				"quote_id" => $order['transaction_id'],
				"tracking_status" => "Deposit completed successfully",
				"raw_data" => $order
			]);

		} catch (\Throwable $th) {
			Log::channel('deposit_error')->error($th->getMessage(), ['error' => $th->getMessage()]);
		}
	}
}

