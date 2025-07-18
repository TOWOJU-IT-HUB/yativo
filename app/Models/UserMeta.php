<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserMeta extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'key', 'value'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // protected $casts = [
    //     'value' => 'array'
    // ];
}
