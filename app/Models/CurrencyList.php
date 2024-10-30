<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CurrencyList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'iso_alpha2',
        'iso_alpha3',
        'iso_numeric',
        'calling_code',
        'currency_code',
        'currency_name',
        'currency_symbol',
    ];
}
