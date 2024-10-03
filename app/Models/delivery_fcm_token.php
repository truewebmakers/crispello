<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class delivery_fcm_token extends Model
{
    use HasFactory;
    protected $primaryKey = 'device_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'token',
        'device_id',
        'driver_id'
    ];

    public function driver()
    {
        return $this->belongsTo(delivery_driver::class, 'driver_id');
    }
}
