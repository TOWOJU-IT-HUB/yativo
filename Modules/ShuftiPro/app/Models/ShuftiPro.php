<?php

namespace Modules\ShuftiPro\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ShuftiPro\Database\factories\ShuftiProFactory;

class ShuftiPro extends Model
{
    use HasFactory, SoftDeletes;

    protected $table ='shuftipro_users';


    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "user_id",
        "reference",
        "status",
        "payload",
        "response",
    ];

    protected $casts = [
        "payload" => "array",
        "response" => "array"
    ];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id", "id");
    }
    
    protected static function newFactory(): ShuftiProFactory
    {
        //return ShuftiProFactory::new();
    }
}
