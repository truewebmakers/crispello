<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_otp extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    public $incrementing = false; // Indicate that the primary key is not an incrementing integer
    protected $keyType = 'string'; // Specify the type of the primary key
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'otp',
        'phoneno',
        'expire_time',
    ];
}
