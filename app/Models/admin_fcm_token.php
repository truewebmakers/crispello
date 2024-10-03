<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class admin_fcm_token extends Model
{
    use HasFactory;
    protected $primaryKey = 'device_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'token',
        'device_id'
    ];
}
