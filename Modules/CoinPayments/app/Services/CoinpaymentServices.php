<?php

namespace Modules\CoinPayments\app\Services;

class CoinpaymentServices
{

    private $private_key = '';
    private $public_key = '';
    private $ch = null;

    public function __construct($private_key, $public_key)
    {
        $this->private_key = $private_key;
        $this->public_key = $public_key;
        $this->ch = null;
    }

    /**
     * Gets the current CoinPayments.net exchange rate. Output includes both crypto and fiat currencies.
     * @param short If short == TRUE (the default), the output won't include the currency names and confirms needed to save bandwidth.
     */
    public function GetRates($short = FALSE)
    {
        $short = $short ? 1 : 0;
        return $this->api_call('rates', array('short' => $short));
    }

    /**
     * Creates a transfer from your account to a specified merchant.<br />
     * @param float amount The amount of the transaction (floating point to 8 decimals).
     * @param string currency The cryptocurrency to withdraw.
     * @param string merchant The merchant ID to send the coins to.
     * @param bool auto_confirm If auto_confirm is TRUE, then the transfer will be performed without an email confirmation.
     */
    public function status($txid = 'CPFE7NQ3M7O0BUOXRDJUGQ7N15')
    {
        $req = array(
            'txid' => $txid,
        );
        return $this->api_call('get_tx_info_multi', $req);
    }

    /**
     * Gets your current coin balances (only includes coins with a balance unless all = TRUE).<br />
     * @param all If all = TRUE then it will return all coins, even those with a 0 balance.
     */
    public function GetBalances($all = true)
    {
        return $this->api_call('balances', array('all' => $all ? 1 : 0));
    }

    /**
     * Creates a basic transaction with minimal parameters.<br />
     * See CreateTransaction for more advanced features.
     * @param float amount The amount of the transaction (floating point to 8 decimals).
     * @param string currency1 The source currency (ie. USD), this is used to calculate the exchange rate for you.
     * @param string currency2 The cryptocurrency of the transaction. currency1 and currency2 can be the same if you don't want any exchange rate conversion.
     * @param string buyer_email Set the buyer's email so they can automatically claim refunds if there is an issue with their payment.
     * @param string address Optionally set the payout address of the transaction. If address is empty then it will follow your payout settings for that coin.
     * @param string ipn_url Optionally set an IPN handler to receive notices about this transaction. If ipn_url is empty then it will use the default IPN URL in your account.
     */
    public function CreateTransactionSimple($amount, $currency1, $currency2, $buyer_email, $address = '', $ipn_url = '')
    {
        $req = array(
            'amount' => $amount,
            'currency1' => $currency1,
            'currency2' => $currency2,
            'buyer_email' => $buyer_email,
            'address' => $address,
            'ipn_url' => $ipn_url,
        );
        // var_dump($req); exit;

        return $this->api_call('create_transaction', $req);
    }

    public function CreateTransaction($req)
    {
        // var_dump($req); exit;
        // See https://www.coinpayments.net/apidoc-create-transaction for parameters
        return $this->api_call('create_transaction', $req);
    }

    /**
     * Creates an address for receiving payments into your CoinPayments Wallet.<br />
     * @param string currency The cryptocurrency to create a receiving address for.
     * @param string ipn_url Optionally set an IPN handler to receive notices about this transaction. If ipn_url is empty then it will use the default IPN URL in your account.
     */
    public function GetCallbackAddress($currency, $ipn_url = '')
    {
        $req = array(
            'currency' => $currency,
            'ipn_url' => $ipn_url,
        );
        $curl = $this->api_call('get_callback_address', $req);
        if($curl['error'] == 'ok') {
            return $curl['result'];
        }

        return $curl;
    }

    /**
     * Creates a withdrawal from your account to a specified address.<br />
     * @param amount The amount of the transaction (floating point to 8 decimals).
     * @param currency The cryptocurrency to withdraw.
     * @param address The address to send the coins to.
     * @param auto_confirm If auto_confirm is TRUE, then the withdrawal will be performed without an email confirmation.
     * @param ipn_url Optionally set an IPN handler to receive notices about this transaction. If ipn_url is empty then it will use the default IPN URL in your account.
     */
    public function CreateWithdrawal($amount, $currency, $address, $auto_confirm = true, $ipn_url = '')
    {
        $req = [
            'wd[1][amount]' => $amount,
            'wd[1][currency]' => $currency,
            'wd[1][address]' => $address,
        ];
        

        return $this->api_call('create_mass_withdrawal', $req);
    }

    public function cancel_withdrawal($id)
    {
        $req = [
            "id" => $id
        ];
        return $this->api_call('cancel_withdrawal', $req);
    }

    public function getDeposits()
    {
        return $this->api_call('get_tx_ids');
    }

    public function withdrawalHistory()
    {
        return $this->api_call('get_withdrawal_history');
    }

    public function convert($amount, $from,  $to)
    {
        $req = array(
            'amount' => $amount,
            'from' => $from,
            'to' => $to
        );
        return $this->api_call('convert', $req);
    }

    public function convert_limits($from = "USDT.TRC20",  $to = "USDT.BEP20")
    {
        $req = array(
            'from' => $from,
            'to' => $to
        );
        return $this->api_call('convert_limits', $req);
    }

    public function get_conversion_info($Id)
    {
        return $this->api_call('get_conversion_info', [
            'id' => $Id
        ]);
    }

    public function withdrawalInfo($id)
    {
        return $this->api_call('get_withdrawal_info', [
            'id' => $id
        ]);
    }

    /**
     * Creates a transfer from your account to a specified merchant.<br />
     * @param amount The amount of the transaction (floating point to 8 decimals).
     * @param currency The cryptocurrency to withdraw.
     * @param merchant The merchant ID to send the coins to.
     * @param auto_confirm If auto_confirm is TRUE, then the transfer will be performed without an email confirmation.
     */
    public function CreateTransfer($amount, $currency, $merchant, $auto_confirm = false)
    {
        $req = array(
            'amount' => $amount,
            'currency' => $currency,
            'merchant' => $merchant,
            'auto_confirm' => $auto_confirm ? 1 : 0,
        );
        return $this->api_call('create_transfer', $req);
    }

    /**
     * Creates a transfer from your account to a specified $PayByName tag.<br />
     * @param amount The amount of the transaction (floating point to 8 decimals).
     * @param currency The cryptocurrency to withdraw.
     * @param pbntag The $PayByName tag to send funds to.
     * @param auto_confirm If auto_confirm is TRUE, then the transfer will be performed without an email confirmation.
     */
    public function SendToPayByName($amount, $currency, $pbntag, $auto_confirm = false)
    {
        $req = array(
            'amount' => $amount,
            'currency' => $currency,
            'pbntag' => $pbntag,
            'auto_confirm' => $auto_confirm ? 1 : 0,
        );
        return $this->api_call('create_transfer', $req);
    }

    private function is_setup()
    {
        return (!empty(getenv('COINPAYMENT_PRIVATE_KEY')) && !empty(getenv('COINPAYMENT_PUBLIC_KEY')));
    }

    private function api_call($cmd, $req = array())
    {
        if (!$this->is_setup()) {
            return array('error' => 'You have not called the Setup function with your private and public keys!');
        }

        // Set the API command and required fields
        $req['version'] = 1;
        $req['cmd'] = $cmd;
        $req['key'] = $this->public_key;
        $req['format'] = 'json'; //supported values are json and xml

        // Generate the query string
        $post_data = http_build_query($req, '', '&');

        // Calculate the HMAC signature on the POST data
        $hmac = hash_hmac('sha512', $post_data, $this->private_key);

        // Create cURL handle and initialize (if needed)
        if ($this->ch === null) {
            $this->ch = curl_init('https://www.coinpayments.net/api.php');
            curl_setopt($this->ch, CURLOPT_FAILONERROR, true);
            curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
        }
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('HMAC: ' . $hmac));
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_data);

        $data = curl_exec($this->ch);
        if ($data !== false) {
            if (PHP_INT_SIZE < 8 && version_compare(PHP_VERSION, '5.4.0') >= 0) {
                // We are on 32-bit PHP, so use the bigint as string option. If you are using any API calls with Satoshis it is highly NOT recommended to use 32-bit PHP
                $dec = json_decode($data, true, 512, JSON_BIGINT_AS_STRING);
            } else {
                $dec = json_decode($data, true);
            }
            if ($dec !== null && count($dec)) {
                return $dec;
            } else {
                // If you are using PHP 5.5.0 or higher you can use json_last_error_msg() for a better error message
                return array('error' => 'Unable to parse JSON result (' . json_last_error() . ')');
            }
        } else {
            return array('error' => 'cURL error: ' . curl_error($this->ch));
        }
    }

    public function ipn($webhookPayload)
    {
        error_log(json_encode((array)$webhookPayload));
        // Fill these in with the information from your CoinPayments.net account.
        $cp_debug_email = '';
        $cp_merchant_id = getenv("COINPAYMENT_MERCHANT_ID");
        $cp_ipn_secret = getenv("COINPAYMENT_IPN_SECRET");

        if (!isset($webhookPayload['ipn_mode']) || $webhookPayload['ipn_mode'] != 'hmac') {
            error_log('IPN Mode is not HMAC');
            die('IPN Mode is not HMAC'); exit;
        }

        $request = file_get_contents('php://input');
        if ($request === FALSE || empty($request)) {
            error_log('Error reading POST data');
            die('Error reading POST data'); exit;
        }

        if (!isset($webhookPayload['merchant']) || $webhookPayload['merchant'] != trim($cp_merchant_id)) {
            error_log('No or incorrect Merchant ID passed');
            die('No or incorrect Merchant ID passed'); exit;
        }

        $hmac = hash_hmac("sha512", $request, trim($cp_ipn_secret));
        if (!hash_equals($hmac, $_SERVER['HTTP_HMAC'])) {
            error_log('HMAC signature does not match');
            die('HMAC signature does not match');
            exit;
        }

        // HMAC Signature verified at this point, load some variables.

        $ipn_type = $webhookPayload['ipn_type'];
        $txn_id = $webhookPayload['txn_id'];
        $status = intval($webhookPayload['status']);
        $status_text = $webhookPayload['status_text'];

        if ($ipn_type != 'deposit') { // Advanced Button payment
            die("IPN OK: Not a deposit payment");
        }


        if ($status >= 100 || $status == 2) {
            // payment is complete or queued for nightly payout, success
            return true;
        } else if ($status < 0) {
            //payment error, this is usually final but payments will sometimes be reopened if there was no exchange rate conversion or with seller consent
            return false;
        } else {
            //payment is pending, you can optionally add a note to the order page
            return false;
        }
        return false;
    }
};
