<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class EncryptionService
{
    /**
     * Encrypt the given value.
     *
     * @param  string  $value
     * @return string
     */
    public function encrypt($value)
    {
        return Crypt::encryptString($value);
    }

    /**
     * Decrypt the given value.
     *
     * @param  string  $encryptedValue
     * @return string
     */
    public function decrypt($encryptedValue)
    {
        try {
            return Crypt::decryptString($encryptedValue);
        } catch (DecryptException $e) {
            // Handle the exception
            return null;
        }
    }
}
