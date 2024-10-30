<?php

namespace Modules\SendMoney\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\SendMoney\Database\factories\ApiQuoteFactory;

class ApiQuote extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];
    
    protected static function newFactory(): ApiQuoteFactory
    {
        //return ApiQuoteFactory::new();
    }
}
