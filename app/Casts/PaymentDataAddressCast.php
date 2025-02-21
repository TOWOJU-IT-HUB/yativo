<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class PaymentDataAddressCast implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        $data = json_decode($value, true);
        if (isset($data['address']) && is_array($data['address'])) {
            $data['address'] = (array) $data['address'];
        }
        return $data;
    }

    public function set($model, $key, $value, $attributes)
    {
        return json_encode($value);
    }
}
