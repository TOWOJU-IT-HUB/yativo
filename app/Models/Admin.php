<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Yadahan\AuthenticationLog\AuthenticationLogable;

class Admin extends Authenticatable
{
    use Notifiable, HasRoles, AuthenticationLogable;

    protected $fillable = [
        'name', 'email', 'password', 'google2fa_secret',
    ];

    protected $hidden = [
        'password', 'remember_token', 'google2fa_secret',
    ];

    public function authentications()
    {
        return true;
    }
}
