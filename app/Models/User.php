<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $primaryKey = '_id';
    protected $hidden = ['created_at', 'updated_at'];
    protected $guard = 'user';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password',
        'name',
        'email',
        'phoneno',
        'dob',
        'image',
        'aniversary_date',
        'gender',
        'disable',
        'user_role',
        'online',
        'available'
    ];

    public function getAuthIdentifierName()
    {
        return '_id';
    }

    public function addresses()
    {
        return $this->hasMany(address::class, 'user_id', '_id'); // 'user_id' is the foreign key, '_id' is the local key
    }
    
    public function carts()
    {
        return $this->hasMany(cart::class, 'user_id', '_id');
    }
    
    public function orders()
    {
        return $this->hasMany(order::class, 'user_id', '_id');
    }
    
    public function feedbacks()
    {
        return $this->hasMany(feedback::class, 'user_id', '_id');
    }
    
    public function fcmTokens()
    {
        return $this->hasMany(user_fcm_token::class, 'user_id', '_id');
    }
    
    public function notifications()
    {
        return $this->hasMany(notification::class, 'user_id', '_id');
    }
    
    public function referralcode()
    {
        return $this->hasOne(ReferralCode::class, 'user_id', '_id');
    }
    
    public function extraSettings()
    {
        return $this->hasMany(extra_setting::class, 'added_by', '_id');
    }
    
    public function deliverAddress()
    {
        return $this->hasOne(address::class, 'user_id', '_id')->orderBy('_id', 'asc');
    }
}    