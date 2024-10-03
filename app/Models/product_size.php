<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class product_size extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'size',
        'actual_price',
        'selling_price',
        'product_id'
    ];

    public function product()
    {
        return $this->belongsTo(product::class, 'product_id');
    }
}
