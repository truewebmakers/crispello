<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class video extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'title',
        'video',
        'category_id',
    ];

    public function categories()
    {
        return $this->belongsTo(product_category::class, 'category_id');
    }
}
