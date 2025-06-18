<?php

namespace App\Services;

class STPSign
{
    private $cadenaOriginal = "";
    private $privatekey     = "";
    private $passphrase     = "";
    private $data           = [];

    public function __construct(array $data, string $privatekey, string $passphrase)
    {
        $this->data        = $data;
        $this->privatekey  = $privatekey;
        $this->passphrase  = $passphrase;

        $this->cadenaOriginal = '||' .
            $data['institucionContraparte'] . '|' .
            $data['empresa'] . '|||' .
            $data['claveRastreo'] . '|' .
            $data['institucionOperante'] . '|' .
            number_format($data['monto'], 2, '.', '') . '|' .
            $data['tipoPago'] . '|' .
            $data['tipoCuentaOrdenante'] . '|' .
            $data['nombreOrdenante'] . '|' .
            $data['cuentaOrdenante'] . '|' .
            $data['rfcCurpOrdenante'] . '|' .
            $data['tipoCuentaBeneficiario'] . '|' .
            $data['nombreBeneficiario'] . '|' .
            $data['cuentaBeneficiario'] . '|' .
            $data['rfcCurpBeneficiario'] . '||||||' .
            $data['conceptoPago'] . '||||||' .
            $data['referenciaNumerica'] . '||||||||0.00||';
    }

    public function getSign(): string
    {
        return $this->generateSignature($this->privatekey, $this->passphrase);
    }

    public function getCadenaOriginal(): string
    {
        return $this->cadenaOriginal;
    }

    private function generateSignature(string $privateKeyPath, string $passphrase): string
    {
        $privateKeyContent = file_get_contents($privateKeyPath);
        $privateKey = openssl_pkey_get_private($privateKeyContent, $passphrase);

        if (!$privateKey) {
            throw new \Exception('Failed to load private key');
        }

        $binarySignature = '';
        $success = openssl_sign($this->cadenaOriginal, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$success) {
            throw new \Exception('Failed to generate digital signature');
        }

        return base64_encode($binarySignature);
    }
}
