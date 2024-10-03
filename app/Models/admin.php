<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class admin extends Authenticatable
{
    use HasFactory, HasApiTokens;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];
    protected $guard = 'admin';

    protected $fillable = [
        'username',
        'password',
        'name',
        'phoneno',
        'email',
        'delivery_charge',
        'free_upto_km',
        'latitude',
        'longitude',
        'delivery_coverage_km',
        'location'
    ];
    public function getAuthIdentifierName()
    {
        return '_id'; // Change to your identifier column name if different
    }
}
