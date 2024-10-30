<?php

namespace Modules\SendMoney\app\Models;

use App\Models\PayinMethods;
use App\Models\payoutMethods;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\SendMoney\Database\factories\SendQuoteFactory;

class SendQuote extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    protected $casts = [
        'action' => 'string',
        'send_amount' => 'float',
        'receive_amount' => 'float',
        'send_gateway' => 'array',
        'receive_gateway' => 'array',
        'send_currency' => 'string',
        'receive_currency' => 'string',
        'transfer_purpose' => 'string',
        'rate' => 'float',
        'user_id' => 'int',
        'beneficiary_id' => 'int',
        'total_amount' => 'float',
        'raw_data' => 'object',
    ];

    public function send_gateway()
    {
        return $this->belongsTo(PayinMethods::class, 'send_gateway', 'id');
    }

    public function receive_gateway()
    {
        // return payoutMethods::whereId(self::beneficiary()->)->first();
    }

    public function purpose()
    {
        return $this->belongsTo(QuoteExtra::class);
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function details()
    {
        return $this->hasMany(SendMoney::class, 'quote_id')->latest()->where('status', 'pending')->orderBy('created_at');
    }

    protected static function newFactory(): SendQuoteFactory
    {
        //return SendQuoteFactory::new();
    }
}
