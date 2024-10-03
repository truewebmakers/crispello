<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class product_category extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'name',
        'image'
    ];

    public function products()
    {
        return $this->hasMany(product::class, 'product_category_id');
    }

    public function videos()
    {
        return $this->hasMany(video::class, 'category_id');
    }
}
