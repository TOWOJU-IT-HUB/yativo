<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class localPaymentTransactions extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'internal_id',
        'transaction_type',
        'amount',
        'provider_response',
        'account_number'
    ];

    protected $casts = [
        'provider_response' => 'array'
    ];
}
