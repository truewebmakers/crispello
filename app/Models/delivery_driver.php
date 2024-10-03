<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class delivery_driver extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name',
        'phoneno',
        'email',
        'profile_image',
        'latitude',
        'longitude',
        'online',
        'available'
    ];

    public function getAuthIdentifierName()
    {
        return '_id'; //your table primary key name
    }

    public function fcmTokens()
    {
        return $this->hasMany(delivery_fcm_token::class, 'driver_id');
    }

    public function deliveryRequests()
    {
        return $this->hasMany(delivery_request::class, 'driver_id');
    }

    public function orders()
    {
        return $this->hasMany(order::class, 'driver_id');
    }
}
