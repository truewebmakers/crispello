<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class notification extends Model
{
    use HasFactory;
    protected $primaryKey = '_id';
    protected $hidden = ['updated_at'];

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'image',
        'type',
        'read',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
