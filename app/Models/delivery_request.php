<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class delivery_request extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $primaryKey = ['order_id', 'driver_id'];
    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = [
        'order_id',
        'order_id',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(order::class, 'order_id');
    }

    public function driver()
    {
        return $this->belongsTo(delivery_driver::class, 'driver_id');
    }
}
