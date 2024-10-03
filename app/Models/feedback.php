<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class feedback extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['updated_at'];

    protected $fillable = [
        'feedback',
        'rating',
        'reply',
        'reply_time',
        'user_id',
        'product_id',
        'combo_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(product::class, 'product_id');
    }

    public function combo()
    {
        return $this->belongsTo(combo::class, 'combo_id');
    }
}
