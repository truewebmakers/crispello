<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class combo extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name',
        'image',
        'actual_price',
        'selling_price',
        'veg',
        'best_seller',
        'recommended',
        'disable',
    ];

    public function comboDetails()
    {
        return $this->hasMany(combo_details::class, 'combo_id');
    }

    public function cartProducts()
    {
        return $this->hasMany(cart_product::class, 'combo_id');
    }

    public function orderProducts()
    {
        return $this->hasMany(order_product::class, 'combo_id');
    }

    public function feedbacks()
    {
        return $this->hasMany(feedback::class, 'combo_id');
    }
}
