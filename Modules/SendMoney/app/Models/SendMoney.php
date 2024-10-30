<?php

namespace Modules\SendMoney\app\Models;

use App\Models\PayinMethods;
use App\Models\payoutMethods;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\SendMoney\Database\factories\SendMoneyFactory;

class SendMoney extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "quote_id",
        "status",
        "raw_data"
    ];

    protected $casts = [
        'raw_data' => 'array'
    ];

    public function send_gateway()
    {
        return $this->belongsTo(PayinMethods::class, 'send_gateway', 'id');
    }

    public function receive_gateway()
    {
        return $this->belongsTo(payoutMethods::class, 'receive_gateway', 'id');
    }

    protected static function newFactory(): SendMoneyFactory
    {
        //return SendMoneyFactory::new();
    }
}
