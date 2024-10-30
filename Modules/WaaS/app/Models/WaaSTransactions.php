<?php

namespace Modules\WaaS\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\WaaS\Database\factories\WaaSTransactionsFactory;

class WaaSTransactions extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];
    
    protected static function newFactory(): WaaSTransactionsFactory
    {
        //return WaaSTransactionsFactory::new();
    }
}
